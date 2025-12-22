document.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    const role = urlParams.get('role');
    const testing = urlParams.get('testing');
    const roomCode = urlParams.get('room_code');
    const userName = urlParams.get('user_name');

    if (testing === 'true') {
        const songList = document.getElementById('song-list');
        if (songList) {
            songList.innerHTML = '<li>123 - Test Song</li>';
        }
    }

    switch (role) {
        case 'creator':
            document.getElementById('creator-view').style.display = 'block';
            break;
        case 'songlist':
            document.getElementById('songlist-view').style.display = 'block';
            break;
        case 'number_pad':
            document.getElementById('number-pad-view').style.display = 'flex';
            initNumberPad(roomCode, userName);
            break;
        default:
            // Handle invalid or missing role
            break;
    }
});

function initNumberPad(roomCode, userName) {
    const display = document.getElementById('entered-number-display');
    const numButtons = document.querySelectorAll('.num-button');
    const controlButtons = {
        play: document.getElementById('num-play'),
        pause: document.getElementById('num-pause'),
        prev: document.getElementById('num-prev'),
        next: document.getElementById('num-next')
    };

    let enteredNumber = '';
    let audioCtx;

    const tones = {
        '1': 261.63, '2': 293.66, '3': 329.63,
        '4': 349.23, '5': 392.00, '6': 440.00,
        '7': 493.88, '8': 523.25, '9': 587.33, '0': 659.25
    };

    function playTone(freq) {
        if (!audioCtx) {
            audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        }
        const oscillator = audioCtx.createOscillator();
        oscillator.type = 'sine';
        oscillator.frequency.setValueAtTime(freq, audioCtx.currentTime);
        oscillator.connect(audioCtx.destination);
        oscillator.start();
        setTimeout(() => oscillator.stop(), 200);
    }

    function updateDisplay() {
        display.textContent = enteredNumber || '-';
    }

    async function sendCommand(action, params = {}) {
        const body = {
            room_code: roomCode,
            user_name: userName,
            action: action,
            ...params
        };

        try {
            const response = await fetch('rooms/update.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body)
            });
            const data = await response.json();
            console.log(data);
        } catch (error) {
            console.error('Error sending command:', error);
        }
    }

    numButtons.forEach(button => {
        button.addEventListener('click', () => {
            const number = button.dataset.number;
            playTone(tones[number]);
            if (enteredNumber.length < 5) {
                enteredNumber += number;
                updateDisplay();
            }
        });
    });

    controlButtons.play.addEventListener('click', () => {
        if (enteredNumber) {
            sendCommand('set_current_song', { song_number: enteredNumber });
            enteredNumber = '';
            updateDisplay();
        } else {
            sendCommand('play', { is_playing: 1 });
        }
    });

    controlButtons.pause.addEventListener('click', () => {
        sendCommand('play', { is_playing: 0 });
    });
    controlButtons.next.addEventListener('click', () => {
        sendCommand('next_song');
    });
    controlButtons.prev.addEventListener('click', () => {
        sendCommand('prev_song');
    });


    updateDisplay();
}
