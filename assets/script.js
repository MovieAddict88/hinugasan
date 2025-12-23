// Professional Karaoke Player with Room System
let songList = [];
let songQueue = [];
let roomQueue = [];
let roomUsers = [];
let recentlyPlayed = [];
let currentPage = 1;
let totalSongs = 0;
let currentSongIndex = -1;
let isPlaying = false;
let isFetching = false;
let currentRoom = null;
let currentUser = null;
let roomSyncInterval = null;
let userSyncInterval = null;
let selectedSongForQueue = null;

// Room constants
const ROOM_SYNC_INTERVAL = 3000;
const USER_SYNC_INTERVAL = 10000;
const API_BASE_URL = '';
const SONGS_PER_PAGE = 50;

// DOM Elements
const welcomeScreen = document.getElementById('welcome-screen');
const karaokeContainer = document.getElementById('karaoke-container');
const songListElement = document.getElementById('song-list');
const prevPageButton = document.getElementById('prev-page');
const nextPageButton = document.getElementById('next-page');
const pageIndicator = document.getElementById('page-indicator');
const queueListElement = document.getElementById('queue-list');
const roomQueueList = document.getElementById('queue-list');
const recentlyPlayedList = document.getElementById('recently-played-list');
const roomUsersList = document.getElementById('room-users-list');
const songNumberInput = document.getElementById('song-number-input');
const songSearchInput = document.getElementById('song-search');
const playButton = document.getElementById('play-button');
const playResumeButton = document.getElementById('play-resume-button');
const nextButton = document.getElementById('next-button');
const pauseButton = document.getElementById('pause-button');
const clearQueueButton = document.getElementById('clear-queue');
const searchButton = document.getElementById('search-button');
const volumeUpButton = document.getElementById('volume-up');
const volumeDownButton = document.getElementById('volume-down');
const currentSongInfo = document.getElementById('current-song-info');
const playerStatus = document.getElementById('player-status');
const queueCount = document.getElementById('queue-count');
const songCount = document.getElementById('song-count');
const userCount = document.getElementById('user-count');
const roomTitleText = document.getElementById('room-title-text');
const roomCodeDisplay = document.getElementById('room-code-display');

// Initialize Video.js player
const player = videojs('video-player', {
    controls: true,
    autoplay: false,
    preload: 'auto',
    responsive: true,
    fluid: true,
    playbackRates: [0.5, 0.75, 1, 1.25, 1.5, 2],
    youtube: {
        ytControls: 2,
        enablePrivacyEnhancedMode: true,
        iv_load_policy: 1
    },
    controlBar: {
        children: [
            'playToggle',
            'volumePanel',
            'currentTimeDisplay',
            'timeDivider',
            'durationDisplay',
            'progressControl',
            'remainingTimeDisplay',
            'playbackRateMenuButton',
            'fullscreenToggle'
        ]
    }
});

// Player ready
player.ready(function() {
    console.log('Karaoke Player initialized');
    updatePlayerStatus('Ready', 'success');
    player.volume(0.8);
    setupKeyboardShortcuts();
});

// ==================== UTILITY FUNCTIONS ====================
function generateRandomName() {
    const adjectives = ['Happy', 'Cool', 'Awesome', 'Super', 'Mega', 'Ultra', 'Epic', 'Legendary'];
    const nouns = ['Singer', 'Star', 'Voice', 'Mic', 'Rockstar', 'Divas', 'Crooner', 'Maestro'];
    const adjective = adjectives[Math.floor(Math.random() * adjectives.length)];
    const noun = nouns[Math.floor(Math.random() * nouns.length)];
    return `${adjective} ${noun}`;
}

function detectDeviceType() {
    const ua = navigator.userAgent;
    if (/mobile|android|iphone|ipad|ipod/i.test(ua)) {
        return 'mobile';
    } else if (/tablet|ipad/i.test(ua)) {
        return 'tablet';
    } else if (/tv|smart-tv|smarttv|appletv/i.test(ua)) {
        return 'tv';
    } else {
        return 'desktop';
    }
}

function showNotification(message, type = 'info') {
    const existingNotifications = document.querySelectorAll('.notification');
    existingNotifications.forEach(n => {
        n.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => n.remove(), 300);
    });
    
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    
    const icons = {
        'success': 'fas fa-check-circle',
        'error': 'fas fa-exclamation-circle',
        'warning': 'fas fa-exclamation-triangle',
        'info': 'fas fa-info-circle'
    };
    
    notification.innerHTML = `
        <i class="${icons[type] || icons.info}"></i>
        <span>${message}</span>
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    }, 4000);
}

function showError(message) {
    showNotification(message, 'error');
}

function updatePlayerStatus(message, type = 'info') {
    const icons = {
        'success': '✓',
        'error': '✗',
        'warning': '⚠',
        'loading': '⏳',
        'playing': '▶',
        'paused': '⏸',
        'info': 'ℹ'
    };
    
    playerStatus.innerHTML = `${icons[type] || icons.info} ${message}`;
    playerStatus.className = `status-${type}`;
}

function showModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'flex';
        setTimeout(() => {
            modal.style.opacity = '1';
        }, 10);
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.opacity = '0';
        setTimeout(() => {
            modal.style.display = 'none';
        }, 300);
    }
}

function backToWelcome() {
    if (confirm('Are you sure you want to leave the room and return to home?')) {
        leaveRoom();
        karaokeContainer.style.display = 'none';
        welcomeScreen.style.display = 'block';
        stopRoomSync();
    }
}

// ==================== ROOM MANAGEMENT FUNCTIONS ====================
async function createRoom() {
    const roomName = document.getElementById('room-name').value.trim();
    const creatorName = document.getElementById('creator-name').value.trim() || generateRandomName();
    const password = document.getElementById('room-password').value;
    const maxUsers = parseInt(document.getElementById('max-users').value);
    const isPublic = document.getElementById('is-public').checked;
    const deviceType = document.getElementById('device-type').value;
    
    if (!roomName) {
        showError('Please enter a room name');
        return;
    }
    
    const data = {
        room_name: roomName,
        creator_name: creatorName,
        password: password,
        device: deviceType,
        max_users: maxUsers,
        is_public: isPublic ? 1 : 0
    };
    
    try {
        const response = await fetch(`${API_BASE_URL}/rooms/create.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            currentRoom = {
                room_code: result.room_code,
                room_id: result.room_id,
                room_name: roomName,
                creator_name: creatorName,
                is_creator: true
            };
            
            currentUser = {
                name: creatorName,
                device: deviceType
            };
            
            saveSession(currentRoom, currentUser);
            showNotification('Room created successfully!', 'success');
            closeModal('create-room-modal');
            enterRoom();
        } else {
            showError(result.message || 'Failed to create room');
        }
    } catch (error) {
        console.error('Error creating room:', error);
        showError('Network error. Please check your connection.');
    }
}

async function joinRoom(roomCode, userName, password = '', deviceType = null) {
    if (!roomCode || !userName) {
        showError('Room code and user name are required');
        return;
    }
    
    const data = {
        room_code: roomCode.toUpperCase(),
        user_name: userName.trim(),
        password: password,
        device: deviceType || detectDeviceType()
    };
    
    try {
        const response = await fetch(`${API_BASE_URL}/rooms/join.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            currentRoom = {
                room_code: roomCode.toUpperCase(),
                room_id: result.room.id,
                room_name: result.room.room_name,
                creator_name: result.room.creator_name,
                is_creator: false
            };
            
            currentUser = {
                name: userName.trim(),
                device: data.device
            };
            
            saveSession(currentRoom, currentUser);
            showNotification('Joined room successfully!', 'success');
            closeModal('join-room-modal');
            enterRoom();
        } else {
            showError(result.message || 'Failed to join room');
        }
    } catch (error) {
        console.error('Error joining room:', error);
        showError('Network error. Please check your connection.');
    }
}

async function quickJoinRoom() {
    const roomCode = document.getElementById('quick-room-code').value.trim();
    const userName = document.getElementById('quick-user-name').value.trim() || generateRandomName();
    const joinType = document.querySelector('input[name="join-type"]:checked').value;

    if (!roomCode) {
        showError('Please enter a room code');
        return;
    }

    if (joinType === 'family') {
        const role = prompt('Enter your role (songlist or number_pad):');
        if (role && (role === 'songlist' || role === 'number_pad')) {
            joinFamilyRoom(roomCode, userName, role);
        } else {
            showError('Invalid role. Please enter "songlist" or "number_pad".');
        }
    } else {
        await joinRoom(roomCode, userName);
    }
}

async function loadRoomList() {
    const search = document.getElementById('room-search').value.trim();
    const roomListContent = document.getElementById('room-list-content');
    
    roomListContent.innerHTML = '<div class="loading-room"><i class="fas fa-spinner fa-spin"></i> Loading rooms...</div>';
    
    try {
        const response = await fetch(`${API_BASE_URL}/rooms/list.php?search=${encodeURIComponent(search)}`);
        const result = await response.json();
        
        if (result.success) {
            const rooms = result.rooms;
            const roomCount = document.getElementById('room-count');
            roomCount.textContent = rooms.length;
            
            if (rooms.length === 0) {
                roomListContent.innerHTML = '<div class="empty-message">No active rooms found</div>';
                return;
            }
            
            let html = '';
            rooms.forEach(room => {
                html += `
                    <div class="room-item">
                        <div class="room-item-header">
                            <div class="room-item-title">
                                <h3><i class="fas fa-users"></i> ${room.room_name}</h3>
                                <p>Created by: ${room.creator_name}</p>
                            </div>
                            <div class="room-item-code">${room.room_code}</div>
                        </div>
                        <div class="room-item-info">
                            <span><i class="fas fa-user"></i> ${room.user_count} user(s)</span>
                            <span><i class="fas fa-clock"></i> ${formatTimeAgo(room.last_active)}</span>
                        </div>
                        <div class="room-item-actions">
                            <button class="btn-join-room" onclick="joinRoomFromList('${room.room_code}')">
                                <i class="fas fa-door-open"></i> Join Room
                            </button>
                        </div>
                    </div>
                `;
            });
            
            roomListContent.innerHTML = html;
        } else {
            showError(result.message || 'Failed to load rooms');
        }
    } catch (error) {
        console.error('Error loading rooms:', error);
        roomListContent.innerHTML = '<div class="empty-message">Error loading rooms</div>';
    }
}

function formatTimeAgo(timestamp) {
    const date = new Date(timestamp);
    const now = new Date();
    const diffMs = now - date;
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    
    if (diffMins < 1) return 'Just now';
    if (diffMins < 60) return `${diffMins} min ago`;
    if (diffHours < 24) return `${diffHours} hour${diffHours > 1 ? 's' : ''} ago`;
    return `${Math.floor(diffHours / 24)} day${Math.floor(diffHours / 24) > 1 ? 's' : ''} ago`;
}

async function joinRoomFromList(roomCode) {
    const userName = prompt('Enter your name:', generateRandomName());
    if (!userName) return;
    
    await joinRoom(roomCode, userName);
}

function enterRoom() {
    welcomeScreen.style.display = 'none';
    karaokeContainer.style.display = 'block';
    
    // Update room info
    roomTitleText.textContent = currentRoom.room_name;
    roomCodeDisplay.textContent = currentRoom.room_code;
    
    // Start room sync
    startRoomSync();
    
    // Load songs
    fetchSongList(1);
}

async function startRoomSync() {
    if (roomSyncInterval) clearInterval(roomSyncInterval);
    if (userSyncInterval) clearInterval(userSyncInterval);
    
    // Initial sync
    await syncRoomStatus();
    
    // Periodic sync
    roomSyncInterval = setInterval(syncRoomStatus, ROOM_SYNC_INTERVAL);
    userSyncInterval = setInterval(updateUserPresence, USER_SYNC_INTERVAL);
    
    // Sync on window focus
    window.addEventListener('focus', syncRoomStatus);
}

function stopRoomSync() {
    if (roomSyncInterval) clearInterval(roomSyncInterval);
    if (userSyncInterval) clearInterval(userSyncInterval);
    roomSyncInterval = null;
    userSyncInterval = null;
    
    window.removeEventListener('focus', syncRoomStatus);
}

async function syncRoomStatus() {
    if (!currentRoom) return;
    
    try {
        const response = await fetch(`${API_BASE_URL}/rooms/status.php?room_code=${currentRoom.room_code}`);
        const result = await response.json();
        
        if (result.success) {
            updateRoomUI(result);
            
            // Sync player if in room mode
            if (currentRoom && !currentRoom.is_creator) {
                syncPlayerWithRoom(result);
            }
        }
    } catch (error) {
        console.error('Error syncing room status:', error);
    }
}

function updateRoomUI(data) {
    // Update room object with current song
    if (data.room) {
		const was_creator = currentRoom.is_creator;
        currentRoom.creator_name = data.room.creator_name;
		currentRoom.is_creator = (currentUser.name === data.room.creator_name);
        currentRoom.current_song_id = data.room.current_song_id;
		if (currentRoom.is_creator && !was_creator) {
			showNotification('You are now the room owner!', 'success');
			saveSession(currentRoom, currentUser);
		}
        currentRoom.current_song_title = data.room.current_song_title;
        currentRoom.current_song_artist = data.room.current_song_artist;
        currentRoom.current_video_source = data.room.current_video_source;
        
        // Update current song info
        if (data.room.current_song_title) {
            currentSongInfo.innerHTML = `
                <strong>${data.room.current_song_title}</strong> - ${data.room.current_song_artist}
                <br><small>Song #${data.room.current_song_number || 'N/A'}</small>
            `;
        }
    }
    
    // Update users
    roomUsers = data.users || [];
    updateUsersList();
    
    // Update queue
    roomQueue = data.queue || [];
    updateRoomQueue();
    
    // Update recently played
    recentlyPlayed = data.history || [];
    updateRecentlyPlayed();
    
    // Update counts
    userCount.textContent = roomUsers.length;
    queueCount.textContent = roomQueue.length;
}

function updateUsersList() {
    const roomUsersList = document.getElementById('room-users-list');
    if (!roomUsersList) return;
    
    roomUsersList.innerHTML = '';
    
    roomUsers.forEach(user => {
        const userBadge = document.createElement('div');
        userBadge.className = `user-badge ${user.user_name === currentRoom.creator_name ? 'creator' : ''}`;
        userBadge.innerHTML = `
            <i class="fas fa-user"></i>
            ${user.user_name}
            <small>(${user.device_type})</small>
        `;
        roomUsersList.appendChild(userBadge);
    });
}

function updateRoomQueue() {
    const queueListElement = document.getElementById('queue-list');
    if (!queueListElement) return;
    
    if (roomQueue.length === 0) {
        queueListElement.innerHTML = '<div class="empty-message">Queue is empty. Add songs to get started!</div>';
        return;
    }
    
    let html = '';
    roomQueue.forEach((song, index) => {
        const isPlaying = song.status === 'playing' || 
                         (currentRoom.current_song_id && song.song_id == currentRoom.current_song_id);
        
        html += `
            <li class="${isPlaying ? 'playing' : ''}" data-song-id="${song.song_id}">
                <div class="queue-item-info">
                    <strong>${index + 1}. ${song.title}</strong>
                    <span class="queue-item-added">
                        <i class="fas fa-user"></i> ${song.user_name}
                    </span>
                </div>
                <div class="queue-item-details">
                    <small>${song.artist} • Song #${song.song_number}</small>
                </div>
                ${currentRoom.is_creator ? `
                <div class="queue-actions">
                    <button class="icon-btn small play-song-btn" onclick="playRoomSong(${song.song_id})">
                        <i class="fas fa-play"></i>
                    </button>
                </div>
                ` : ''}
            </li>
        `;
    });
    
    queueListElement.innerHTML = html;
}

function updateRecentlyPlayed() {
    const recentlyPlayedList = document.getElementById('recently-played-list');
    if (!recentlyPlayedList) return;
    
    if (recentlyPlayed.length === 0) {
        recentlyPlayedList.innerHTML = '<li class="empty-message">No songs played yet</li>';
        return;
    }
    
    let html = '';
    recentlyPlayed.forEach(song => {
        const playedTime = new Date(song.played_at).toLocaleTimeString([], { 
            hour: '2-digit', 
            minute: '2-digit' 
        });
        
        html += `
            <li>
                <span>${song.title} - ${song.artist}</span>
                <small>${playedTime}</small>
            </li>
        `;
    });
    
    recentlyPlayedList.innerHTML = html;
}

async function syncPlayerWithRoom(roomData) {
    if (!roomData.room || !roomData.room.current_video_source) {
        // No song playing in room
        return;
    }
    
    const youtubeId = extractYouTubeId(roomData.room.current_video_source);
    if (!youtubeId) {
        console.error('Invalid YouTube URL in room data');
        return;
    }
    
    // Check if we're already playing this video
    const currentSrc = player.currentSrc();
    if (currentSrc && currentSrc.includes(youtubeId)) {
        // Already playing this video, just sync time if needed
        return;
    }
    
    // Load and play the new video
    try {
        player.src({
            src: `https://www.youtube.com/watch?v=${youtubeId}`,
            type: 'video/youtube'
        });
        
        // Wait for player to be ready
        player.ready(() => {
            if (roomData.room.is_playing) {
                player.play();
                isPlaying = true;
                updatePlayerStatus('Playing', 'playing');
            }
        });
    } catch (error) {
        console.error('Error syncing player with room:', error);
    }
}

async function updateUserPresence() {
    if (!currentRoom || !currentUser) return;
    
    try {
        await fetch(`${API_BASE_URL}/rooms/update.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                room_code: currentRoom.room_code,
                user_name: currentUser.name,
                action: 'sync'
            })
        });
    } catch (error) {
        console.error('Error updating user presence:', error);
    }
}

// ==================== SESSION MANAGEMENT ====================
function saveSession(room, user) {
    try {
        localStorage.setItem('karaokeRoom', JSON.stringify(room));
        localStorage.setItem('karaokeUser', JSON.stringify(user));
    } catch (error) {
        console.error('Error saving session to localStorage:', error);
    }
}

function clearSession() {
    try {
        localStorage.removeItem('karaokeRoom');
        localStorage.removeItem('karaokeUser');
    } catch (error) {
        console.error('Error clearing session from localStorage:', error);
    }
}

function loadSession() {
    try {
        const room = localStorage.getItem('karaokeRoom');
        const user = localStorage.getItem('karaokeUser');
        if (room && user) {
            return {
                room: JSON.parse(room),
                user: JSON.parse(user)
            };
        }
        return null;
    } catch (error) {
        console.error('Error loading session from localStorage:', error);
        return null;
    }
}

async function rejoinRoom(session) {
    if (!session || !session.room || !session.user) {
        return;
    }

    currentRoom = session.room;
    currentUser = session.user;

    // Verify the room and user are still valid on the backend
    try {
        const response = await fetch(`${API_BASE_URL}/rooms/status.php?room_code=${currentRoom.room_code}`);
        const result = await response.json();

        if (result.success) {
            const userInRoom = result.users.some(u => u.user_name === currentUser.name);
            if (userInRoom) {
                showNotification(`Welcome back to ${currentRoom.room_name}!`, 'info');
                enterRoom();
            } else {
                 // User is no longer in the room on the backend, so we should clear the session
                clearSession();
                currentRoom = null;
                currentUser = null;
            }
        } else {
            // Room no longer exists, clear session
            clearSession();
            currentRoom = null;
            currentUser = null;
        }
    } catch (error) {
        console.error('Error rejoining room:', error);
        clearSession();
        currentRoom = null;
        currentUser = null;
    }
}


async function leaveRoom() {
    if (!currentRoom || !currentUser) return;

    if (confirm('Are you sure you want to leave the room?')) {
        try {
            await fetch(`${API_BASE_URL}/rooms/update.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    room_code: currentRoom.room_code,
                    user_name: currentUser.name,
                    action: 'leave'
                })
            });

            stopRoomSync();
            currentRoom = null;
            currentUser = null;
            clearSession();
            showNotification('Left the room', 'info');

            // Go back to the welcome screen
            karaokeContainer.style.display = 'none';
            welcomeScreen.style.display = 'block';

        } catch (error) {
            console.error('Error leaving room:', error);
        }
    }
}

// ==================== SONG QUEUE FUNCTIONS ====================
async function addSongToRoomQueue(song) {
    if (!currentRoom) {
        // Solo mode
        addSongToQueue(song);
        return;
    }
    
    // Store the selected song
    selectedSongForQueue = song;
    
    // Show the modal
    showAddSongModal(song);
}

function showAddSongModal(song) {
    const songPreview = document.getElementById('selected-song-preview');
    const confirmBtn = document.getElementById('confirm-add-song');

    if (!songPreview || !confirmBtn) {
        console.error('Add song modal elements not found!');
        showError('An error occurred preparing the song to be added.');
        return;
    }

    songPreview.innerHTML = `
        <p>You are adding:</p>
        <h3>${song.title}</h3>
        <p>by ${song.artist}</p>
        <p><small>Song #${song.song_number}</small></p>
    `;
    
    const handler = () => {
        addSongToRoomQueueConfirmed(song);
        closeModal('add-song-modal');
    };

    // To prevent multiple listeners from being attached, we replace the button
    const newConfirmBtn = confirmBtn.cloneNode(true);
    confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
    newConfirmBtn.addEventListener('click', handler);

    showModal('add-song-modal');
}

async function addSongToRoomQueueConfirmed(song) {
    if (!currentRoom || !currentUser) return;
    
    console.log('Adding song to room queue:', song);
    
    try {
        // Get the song ID from the database using song number
        const songResponse = await fetch(`${API_BASE_URL}/songs.php?search=${encodeURIComponent(song.song_number)}&limit=1`);
        const songData = await songResponse.json();
        
        if (!songData.songs || songData.songs.length === 0) {
            showError('Song not found in database');
            return;
        }
        
        const songId = songData.songs[0].id;
        
        const response = await fetch(`${API_BASE_URL}/rooms/update.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                room_code: currentRoom.room_code,
                user_name: currentUser.name,
                action: 'add_song',
                song_id: songId
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification(`Added "${song.title}" to room queue`, 'success');
            // Sync room status to update the queue display
            await syncRoomStatus();
        } else {
            showError(result.message || 'Failed to add song to room queue');
        }
    } catch (error) {
        console.error('Error adding song to room queue:', error);
        showError('Network error. Please try again.');
    }
}

async function playRoomSong(songId) {
    if (!currentRoom || !currentUser) return;
    
    try {
        const response = await fetch(`${API_BASE_URL}/rooms/update.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                room_code: currentRoom.room_code,
                user_name: currentUser.name,
                action: 'set_current_song',
                song_id: songId
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('Playing song in room', 'success');
            await syncRoomStatus();
        } else {
            showError(result.message || 'Failed to play song');
        }
    } catch (error) {
        console.error('Error playing room song:', error);
        showError('Network error. Please try again.');
    }
}

// ==================== UI FUNCTIONS ====================
function showCreateRoom() {
    document.getElementById('creator-name').value = generateRandomName();
    showModal('create-room-modal');
}

function showJoinRoom() {
    document.getElementById('join-user-name').value = generateRandomName();
    document.getElementById('join-device-type').value = detectDeviceType();
    showModal('join-room-modal');
}

function showJoinFamilyRoom() {
    document.getElementById('join-family-user-name').value = generateRandomName();
    showModal('join-family-room-modal');
}

async function joinFamilyRoom(roomCode, userName, role) {
    if (!roomCode || !userName || !role) {
        showError('Room code, user name, and role are required');
        return;
    }

    const data = {
        room_code: roomCode.toUpperCase(),
        user_name: userName.trim(),
        role: role,
        join_as: 'family'
    };

    try {
        const response = await fetch(`${API_BASE_URL}/rooms/join.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (result.success) {
            showNotification('Joined family room successfully!', 'success');
            window.location.href = `family.html?room_code=${roomCode.toUpperCase()}&user_name=${userName.trim()}&role=${role}`;
        } else {
            showError(result.message || 'Failed to join family room');
        }
    } catch (error) {
        console.error('Error joining family room:', error);
        showError('Network error. Please check your connection.');
    }
}

function showRoomList() {
    showModal('room-list-modal');
    loadRoomList();
}

function showFamilyMode() {
    document.getElementById('family-creator-name').value = generateRandomName();
    showModal('create-family-room-modal');
}

async function createFamilyRoom() {
    const roomName = document.getElementById('family-room-name').value;
    const creatorName = document.getElementById('family-creator-name').value.trim();
    const password = document.getElementById('family-room-password').value;

    if (!creatorName) {
        showError('Please enter your name');
        return;
    }

    const data = {
        room_name: roomName,
        creator_name: creatorName,
        password: password,
        is_public: false,
        mode: 'family'
    };

    try {
        const response = await fetch(`${API_BASE_URL}/rooms/create.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (result.success) {
            showNotification('Family room created successfully!', 'success');
            window.location.href = `family.html?room_code=${result.room_code}&user_name=${creatorName}&role=creator`;
        } else {
            showError(result.message || 'Failed to create family room');
        }
    } catch (error) {
        console.error('Error creating family room:', error);
        showError('Network error. Please check your connection.');
    }
}

function enterSoloMode() {
    currentRoom = null;
    currentUser = {
        name: generateRandomName(),
        device: detectDeviceType()
    };
    
    welcomeScreen.style.display = 'none';
    karaokeContainer.style.display = 'block';
    
    // Hide room header in solo mode
    document.querySelector('.room-header').style.display = 'none';
    
    // Load songs
    fetchSongList(1);
}

// Copy Functions
function copyRoomCode() {
    if (!currentRoom) return;
    
    navigator.clipboard.writeText(currentRoom.room_code)
        .then(() => showNotification('Room code copied!', 'success'))
        .catch(() => showError('Failed to copy room code'));
}

function copyInviteCode() {
    copyRoomCode();
}

function copyInviteLink() {
    if (!currentRoom) return;
    
    const inviteLink = `${window.location.origin}?room=${currentRoom.room_code}`;
    navigator.clipboard.writeText(inviteLink)
        .then(() => showNotification('Invite link copied!', 'success'))
        .catch(() => showError('Failed to copy invite link'));
}

function showInviteModal() {
    if (!currentRoom) {
        showError('You are not in a room');
        return;
    }
    
    document.getElementById('invite-code').textContent = currentRoom.room_code;
    document.getElementById('invite-link').value = `${window.location.origin}?room=${currentRoom.room_code}`;
    showModal('invite-modal');
}

// Share Functions
function shareViaWhatsApp() {
    if (!currentRoom) return;
    
    const text = `Join my karaoke room "${currentRoom.room_name}"! Code: ${currentRoom.room_code}`;
    const url = `https://wa.me/?text=${encodeURIComponent(text)}`;
    window.open(url, '_blank');
}

function shareViaFacebook() {
    if (!currentRoom) return;
    
    const text = `Join my karaoke room "${currentRoom.room_name}"! Code: ${currentRoom.room_code}`;
    const url = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(window.location.origin)}&quote=${encodeURIComponent(text)}`;
    window.open(url, '_blank');
}

function shareViaTelegram() {
    if (!currentRoom) return;
    
    const text = `Join my karaoke room "${currentRoom.room_name}"! Code: ${currentRoom.room_code}`;
    const url = `https://t.me/share/url?url=${encodeURIComponent(window.location.origin)}&text=${encodeURIComponent(text)}`;
    window.open(url, '_blank');
}

// ==================== SONG MANAGEMENT FUNCTIONS ====================
async function fetchSongList(page = 1, search = '') {
    if (isFetching) return;
    
    isFetching = true;
    updatePlayerStatus('Loading songs...', 'loading');
    songListElement.innerHTML = '<div class="empty-message loading">Loading songs...</div>';
    
    try {
        const response = await fetch(`${API_BASE_URL}/songs.php?page=${page}&limit=${SONGS_PER_PAGE}&search=${search}`);
        if (!response.ok) {
            throw new Error(`HTTP error! Status: ${response.status}`);
        }
        
        const data = await response.json();
        if (data && Array.isArray(data.songs)) {
            songList = data.songs;
            totalSongs = data.total;
            currentPage = page;
            
            populateSongList();
            updateSongCount();
            updatePaginationControls();
            updatePlayerStatus(`Loaded ${songList.length} of ${totalSongs} songs`, 'success');
        } else {
            throw new Error('Invalid data format received from API');
        }
    } catch (error) {
        console.error('Error fetching song list:', error);
        updatePlayerStatus('Error loading songs', 'error');
        showError('Failed to load songs. Please check the API connection.');
        songListElement.innerHTML = '<div class="empty-message">Failed to load songs. Please try refreshing.</div>';
    } finally {
        isFetching = false;
    }
}

function updatePaginationControls() {
    const totalPages = Math.ceil(totalSongs / SONGS_PER_PAGE);
    pageIndicator.textContent = `Page ${currentPage} / ${totalPages}`;

    prevPageButton.disabled = currentPage === 1;
    nextPageButton.disabled = currentPage === totalPages;
}

function populateSongList(filter = '') {
    songListElement.innerHTML = '';
    
    if (songList.length === 0) {
        songListElement.innerHTML = '<div class="empty-message">No songs found for your search.</div>';
        return;
    }
    
    songList.forEach(song => {
        const li = document.createElement('li');
        li.innerHTML = `
            <span class="song-number">${song.song_number}</span>
            <div class="song-info">
                <span class="song-title">${song.title}</span>
                <span class="song-artist">${song.artist}</span>
            </div>
            <button class="add-to-queue-btn" data-song-number="${song.song_number}" aria-label="Add ${song.title} to queue">
                <i class="fas fa-plus"></i> Add
            </button>
        `;
        
        li.addEventListener('click', (e) => {
            if (!e.target.closest('button')) {
                showSongPreview(song);
            }
        });
        
        const addBtn = li.querySelector('.add-to-queue-btn');
        addBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            console.log('Add button clicked for song:', song);
            addSongToRoomQueue(song);
        });
        
        songListElement.appendChild(li);
    });
}

function addSongToQueue(song) {
    if (songQueue.some(q => q.song_number === song.song_number)) {
        showNotification(`${song.title} is already in the queue`, 'warning');
        return;
    }
    
    songQueue.push(song);
    updateQueueCount();
    renderQueue();
    showNotification(`Added "${song.title}" to queue`, 'success');
    
    if (songQueue.length === 1 && !isPlaying) {
        playCurrentSong();
    }
}

function updateSongCount() {
    songCount.textContent = totalSongs;
}

function updateQueueCount() {
    if (currentRoom) {
        queueCount.textContent = roomQueue.length;
    } else {
        queueCount.textContent = songQueue.length;
    }
}

function renderQueue() {
    if (!queueListElement) return;
    
    if (songQueue.length === 0) {
        queueListElement.innerHTML = '<div class="empty-message">Queue is empty. Add songs to get started!</div>';
        return;
    }
    
    let html = '';
    songQueue.forEach((song, index) => {
        const isCurrent = index === 0 && isPlaying;
        html += `
            <li class="${isCurrent ? 'playing' : ''}">
                <div>
                    <strong>${index + 1}. ${song.title}</strong> - ${song.artist}
                    ${isCurrent ? '<span class="now-playing-badge">Now Playing</span>' : ''}
                </div>
                <div class="queue-actions">
                    <button class="icon-btn small remove-btn" onclick="removeFromQueue(${index})">
                        <i class="fas fa-times"></i>
                    </button>
                    ${!isCurrent ? `<button class="icon-btn small play-now-btn" onclick="playFromQueue(${index})">
                        <i class="fas fa-play"></i>
                    </button>` : ''}
                </div>
            </li>
        `;
    });
    
    queueListElement.innerHTML = html;
}

function removeFromQueue(index) {
    if (index >= 0 && index < songQueue.length) {
        const removedSong = songQueue.splice(index, 1)[0];
        updateQueueCount();
        renderQueue();
        showNotification(`Removed "${removedSong.title}" from queue`, 'info');
        
        if (index === 0 && isPlaying) {
            player.pause();
            nextSong();
        }
    }
}

function playFromQueue(index) {
    if (index > 0 && index < songQueue.length) {
        const [song] = songQueue.splice(index, 1);
        songQueue.unshift(song);
        updateQueueCount();
        renderQueue();
        playCurrentSong();
    }
}

function showSongPreview(song) {
    currentSongInfo.innerHTML = `
        <strong>${song.title}</strong> - ${song.artist}
        <br><small>Song #${song.song_number}</small>
        <br><button class="preview-play-btn" onclick="playSongImmediately(${JSON.stringify(song).replace(/"/g, '&quot;')})">
            <i class="fas fa-play"></i> Play Now
        </button>
    `;
}

function playSongImmediately(song) {
    if (currentRoom) {
        // In room mode, add to queue and play
        addSongToRoomQueue(song);
        // Then set as current song
        setTimeout(async () => {
            // Get the song ID from the database
            const songResponse = await fetch(`${API_BASE_URL}/songs.php?search=${encodeURIComponent(song.song_number)}&limit=1`);
            const songData = await songResponse.json();
            
            if (songData.songs && songData.songs.length > 0) {
                const songId = songData.songs[0].id;
                await playRoomSong(songId);
            }
        }, 1000);
    } else {
        // Solo mode
        if (confirm(`Play "${song.title}" immediately? This will clear the current queue.`)) {
            songQueue = [song];
            updateQueueCount();
            renderQueue();
            playCurrentSong();
        }
    }
}

function extractYouTubeId(url) {
    if (!url) return null;
    
    const patterns = [
        /(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]{11})/,
        /youtube\.com\/v\/([a-zA-Z0-9_-]{11})/,
        /youtube\.com\/.*[?&]v=([a-zA-Z0-9_-]{11})/,
        /^([a-zA-Z0-9_-]{11})$/
    ];
    
    for (const pattern of patterns) {
        const match = url.match(pattern);
        if (match && match[1]) {
            return match[1];
        }
    }
    
    return null;
}

async function playCurrentSong() {
    if (currentRoom) {
        // In room mode, sync with room
        await syncRoomStatus();
        return;
    }
    
    // Solo mode
    if (songQueue.length === 0) {
        updatePlayerStatus('Queue is empty', 'info');
        currentSongInfo.innerHTML = '<em>Select a song to begin</em>';
        return;
    }
    
    const currentSong = songQueue[0];
    currentSongInfo.innerHTML = `
        <strong>${currentSong.title}</strong> - ${currentSong.artist}
        <br><small>Song #${currentSong.song_number}</small>
    `;
    
    updatePlayerStatus(`Playing: ${currentSong.title}`, 'playing');
    
    const youtubeId = extractYouTubeId(currentSong.video_source);
    
    if (!youtubeId) {
        showError('Invalid YouTube URL format');
        return;
    }
    
    try {
        player.src({
            src: `https://www.youtube.com/watch?v=${youtubeId}`,
            type: 'video/youtube'
        });
        
        await player.play();
        isPlaying = true;
        renderQueue();
        playButton.classList.add('hidden');
        playResumeButton.classList.add('hidden');
        pauseButton.classList.remove('hidden');
    } catch (error) {
        console.error('Error playing video:', error);
        showError('Error playing video. Please try another song.');
        nextSong();
    }
}

function nextSong() {
    if (currentRoom) {
        // In room mode, trigger next song
        triggerRoomNextSong();
        return;
    }
    
    // Solo mode
    if (songQueue.length > 0) {
        const finishedSong = songQueue.shift();
        updateQueueCount();
        
        if (songQueue.length > 0) {
            playCurrentSong();
        } else {
            player.reset();
            isPlaying = false;
            currentSongInfo.innerHTML = '<em>Select a song to begin</em>';
            updatePlayerStatus('Queue finished', 'info');
            showNotification('Queue finished', 'info');
            playButton.classList.remove('hidden');
            playResumeButton.classList.add('hidden');
            pauseButton.classList.add('hidden');
        }
        
        renderQueue();
    }
}

async function triggerRoomNextSong() {
    if (!currentRoom) return;
    
    try {
        const response = await fetch(`${API_BASE_URL}/rooms/update.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                room_code: currentRoom.room_code,
                user_name: currentUser.name,
                action: 'next_song'
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('Next song triggered', 'success');
            await syncRoomStatus();
        }
    } catch (error) {
        console.error('Error triggering next song:', error);
    }
}

function setupKeyboardShortcuts() {
    document.addEventListener('keydown', (e) => {
        if (e.target.tagName === 'INPUT') return;
        
        switch(e.key.toLowerCase()) {
            case ' ':
                e.preventDefault();
                togglePlayPause();
                break;
            case 'arrowright':
                if (e.ctrlKey) {
                    e.preventDefault();
                    nextSong();
                }
                break;
            case 'arrowup':
                e.preventDefault();
                adjustVolume(0.1);
                break;
            case 'arrowdown':
                e.preventDefault();
                adjustVolume(-0.1);
                break;
            case 'escape':
                if (player.isFullscreen()) {
                    player.exitFullscreen();
                }
                break;
        }
    });
}

function togglePlayPause() {
    if (player.paused()) {
        player.play();
    } else {
        player.pause();
    }
}

function adjustVolume(delta) {
    const currentVolume = player.volume();
    const newVolume = Math.max(0, Math.min(1, currentVolume + delta));
    player.volume(newVolume);
    showNotification(`Volume: ${Math.round(newVolume * 100)}%`, 'info');
}

// ==================== EVENT LISTENERS ====================
document.addEventListener('DOMContentLoaded', async () => {
    // Set default user name in modals
    const defaultName = generateRandomName();
    document.getElementById('creator-name').value = defaultName;
    document.getElementById('join-user-name').value = defaultName;
    document.getElementById('quick-user-name').value = defaultName;
    
    // Set default device type
    const deviceType = detectDeviceType();
    document.getElementById('device-type').value = deviceType;
    document.getElementById('join-device-type').value = deviceType;

    // Check for a saved session
    const session = loadSession();
    if (session) {
        await rejoinRoom(session);
        await fetchSongList(1);
        return; 
    }
    
    // Check for room code in URL
    const urlParams = new URLSearchParams(window.location.search);
    const roomCode = urlParams.get('room');
    if (roomCode) {
        const userName = prompt('Enter your name to join the room:', generateRandomName());
        if (userName) {
            await joinRoom(roomCode, userName);
        }
    }
    
    // Form submissions
    document.getElementById('create-room-form').addEventListener('submit', (e) => {
        e.preventDefault();
        createRoom();
    });
    
    document.getElementById('join-room-form').addEventListener('submit', (e) => {
        e.preventDefault();
        const roomCode = document.getElementById('join-room-code').value.trim();
        const userName = document.getElementById('join-user-name').value.trim();
        const password = document.getElementById('join-password').value;
        const deviceType = document.getElementById('join-device-type').value;
        
        joinRoom(roomCode, userName, password, deviceType);
    });

    document.getElementById('join-family-room-form').addEventListener('submit', (e) => {
        e.preventDefault();
        const roomCode = document.getElementById('join-family-room-code').value.trim();
        const userName = document.getElementById('join-family-user-name').value.trim();
        const role = document.getElementById('join-family-role').value;

        joinFamilyRoom(roomCode, userName, role);
    });

    document.getElementById('create-family-room-form').addEventListener('submit', (e) => {
        e.preventDefault();
        createFamilyRoom();
    });
    
    // Search input listeners
    songSearchInput.addEventListener('input', debounce((e) => {
        const searchTerm = e.target.value.trim();
        songList = [];
        currentPage = 1;
        fetchSongList(1, searchTerm);
    }, 300));
    
    songNumberInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            const searchTerm = songNumberInput.value.trim();
            if (searchTerm) {
                const song = songList.find(s => 
                    s.song_number === searchTerm || 
                    s.title.toLowerCase().includes(searchTerm.toLowerCase()) ||
                    s.artist.toLowerCase().includes(searchTerm.toLowerCase())
                );
                
                if (song) {
                    addSongToRoomQueue(song);
                    songNumberInput.value = '';
                    songNumberInput.focus();
                } else {
                    showError('Song not found! Try searching by number, title, or artist.');
                }
            }
        }
    });
    
    // Room search
    const roomSearchInput = document.getElementById('room-search');
    if (roomSearchInput) {
        roomSearchInput.addEventListener('input', debounce(loadRoomList, 500));
    }
    
    // Button listeners
    prevPageButton.addEventListener('click', () => {
        if (currentPage > 1) {
            fetchSongList(currentPage - 1, songSearchInput.value.trim());
        }
    });

    nextPageButton.addEventListener('click', () => {
        const totalPages = Math.ceil(totalSongs / SONGS_PER_PAGE);
        if (currentPage < totalPages) {
            fetchSongList(currentPage + 1, songSearchInput.value.trim());
        }
    });

    playButton.addEventListener('click', () => {
        const searchTerm = songNumberInput.value.trim();
        if (searchTerm) {
            const song = songList.find(s => 
                s.song_number === searchTerm || 
                s.title.toLowerCase().includes(searchTerm.toLowerCase()) ||
                s.artist.toLowerCase().includes(searchTerm.toLowerCase())
            );
            
            if (song) {
                addSongToRoomQueue(song);
                songNumberInput.value = '';
                songNumberInput.focus();
            } else {
                showError('Song not found! Try searching by number, title, or artist.');
            }
        }
    });

    playResumeButton.addEventListener('click', togglePlayPause);
    
    nextButton.addEventListener('click', nextSong);
    
    pauseButton.addEventListener('click', () => {
        togglePlayPause();
    });
    
    clearQueueButton.addEventListener('click', () => {
        if (currentRoom) {
            showError('Cannot clear room queue. Only room creator can manage queue.');
            return;
        }
        
        if (songQueue.length > 0) {
            if (confirm('Are you sure you want to clear the entire queue?')) {
                const queueLength = songQueue.length;
                songQueue = [];
                updateQueueCount();
                renderQueue();
                player.reset();
                isPlaying = false;
                currentSongInfo.innerHTML = '<em>Select a song to begin</em>';
                updatePlayerStatus('Queue cleared', 'info');
                showNotification(`Cleared ${queueLength} songs from queue`, 'info');
            }
        }
    });
    
    searchButton.addEventListener('click', () => {
        const searchTerm = songNumberInput.value.trim();
        songList = [];
        currentPage = 1;
        fetchSongList(1, searchTerm);
    });
    
    volumeUpButton.addEventListener('click', () => adjustVolume(0.1));
    volumeDownButton.addEventListener('click', () => adjustVolume(-0.1));
    
    // Video player events
    player.on('ended', () => {
        console.log('Video ended, playing next song');
        nextSong();
    });
    
    player.on('error', (e) => {
        console.error('Video player error:', player.error());
        updatePlayerStatus('Playback error', 'error');
        showError('Error playing video. Please try another song.');
        
        if (currentRoom) {
            setTimeout(triggerRoomNextSong, 2000);
        } else {
            setTimeout(nextSong, 2000);
        }
    });
    
    player.on('playing', () => {
        isPlaying = true;
        updatePlayerStatus('Playing', 'playing');
        playButton.classList.add('hidden');
        playResumeButton.classList.add('hidden');
        pauseButton.classList.remove('hidden');
    });
    
    player.on('pause', () => {
        isPlaying = false;
        updatePlayerStatus('Paused', 'paused');
        playButton.classList.add('hidden');
        playResumeButton.classList.remove('hidden');
        pauseButton.classList.add('hidden');
    });
    
    player.on('waiting', () => {
        updatePlayerStatus('Buffering...', 'loading');
    });
    
    player.on('canplay', () => {
        updatePlayerStatus('Ready to play', 'info');
    });
    
    // Fetch initial song list
    await fetchSongList(1);
});

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}