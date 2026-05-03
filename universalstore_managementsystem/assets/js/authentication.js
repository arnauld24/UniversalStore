// Toggle design(login/signup shift)
const container = document.querySelector('.container');
const signupBtn = document.querySelector('.signup-btns');
const loginBtn = document.querySelector('.login-btns');
 
signupBtn.addEventListener('click', () => {
    container.classList.add('active');
});

loginBtn.addEventListener('click', () => {
    container.classList.remove('active');
});

//password show 

// 1. Get ALL toggle SPAN elements
const togglePassword = document.querySelectorAll('.togglePassword');

// 2. Add an event listener to the span element
togglePassword.forEach(spanElement => {
spanElement.addEventListener('click', function() {
    const container = this.closest('.input-box'); 
    const passwordField = container.querySelector('.password');
    const iconElement = this.querySelector('i'); // Get the <i> tag inside the button

    const isHidden = passwordField.type === 'password';

    if (isHidden) {
        // ACTION: SHOW PASSWORD
        passwordField.type = 'text';

        // Swap Icon to 'Visible Eye'
        iconElement.classList.remove('fa-eye-slash');
        iconElement.classList.add('fa-eye');
        
        // Optional: Change color to indicate it's active/open
        iconElement.style.color = '#101b24ff'; 
    } else {
        // ACTION: HIDE PASSWORD
        passwordField.type = 'password';

        // Swap Icon back to 'Hidden Eye'
        iconElement.classList.remove('fa-eye');
        iconElement.classList.add('fa-eye-slash');
        
        // Optional: Change color back to default
        iconElement.style.color = '#101b24ff'; 
    }
});
});

