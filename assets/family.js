document.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    const role = urlParams.get('role');
    const roomCode = urlParams.get('room_code');
    const userName = urlParams.get('user_name');

    // Hide all views by default
    document.getElementById('creator-view').style.display = 'none';
    document.getElementById('songlist-view').style.display = 'none';
    document.getElementById('number-pad-view').style.display = 'none';

    switch (role) {
        case 'creator':
            document.getElementById('creator-view').style.display = 'block';
            // Initialize creator view specific scripts if any
            break;
        case 'songlist':
            document.getElementById('songlist-view').style.display = 'flex';
            // Initialize songlist view specific scripts if any
            break;
        case 'number_pad':
            document.getElementById('number-pad-view').style.display = 'flex';
            initNumberPad(roomCode, userName);
            break;
        default:
            // Optional: Redirect to an error page or show a default view
            console.error('Invalid or missing role specified.');
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
        next: document.getElementById('num-next'),
        backspace: document.getElementById('num-backspace'),
        clear: document.getElementById('num-clear')
    };

    let enteredNumber = '';
    let audioCtx;

    const tones = {
        '1': 350, '2': 392, '3': 440, '4': 493, '5': 523,
        '6': 587, '7': 659, '8': 698, '9': 784, '0': 830,
        'backspace': 200, 'clear': 200
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
        setTimeout(() => oscillator.stop(), 150);
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
            if (!data.success) {
                console.error('Failed to send command:', data.message);
            }
        } catch (error) {
            console.error('Error sending command:', error);
        }
    }

    numButtons.forEach(button => {
        button.addEventListener('click', () => {
            const number = button.dataset.number;
            if (number) {
                playTone(tones[number]);
                if (enteredNumber.length < 5) {
                    enteredNumber += number;
                    updateDisplay();
                }
            }
        });
    });

    controlButtons.play.addEventListener('click', () => {
        playTone(500);
        if (enteredNumber) {
            sendCommand('set_current_song', { song_number: enteredNumber });
            enteredNumber = '';
            updateDisplay();
        } else {
            sendCommand('play', { is_playing: 1 });
        }
    });

    controlButtons.pause.addEventListener('click', () => sendCommand('play', { is_playing: 0 }));
    controlButtons.next.addEventListener('click', () => sendCommand('next_song'));
    controlButtons.prev.addEventListener('click', () => sendCommand('prev_song'));

    controlButtons.backspace.addEventListener('click', () => {
        playTone(tones['backspace']);
        enteredNumber = enteredNumber.slice(0, -1);
        updateDisplay();
    });

    controlButtons.clear.addEventListener('click', () => {
        playTone(tones['clear']);
        enteredNumber = '';
        updateDisplay();
    });

    updateDisplay();
}
