
// enable otp fields autofill
const otp = document.querySelectorAll('.otp_field');

// focus on first input
if (otp.length > 0) {
    otp[0].focus();
}

otp.forEach((field, index) => {
    field.addEventListener('input', (e) => {
        let value = e.target.value;
        // Only keep the last entered digit to prevent multiple numbers in one field
        if (value.length > 1) {
            value = value.slice(-1);
        }
        // Only allow numeric input, clear if non-numeric
        if (!/^\d$/.test(value)) {
            e.target.value = '';
            return;
        }
        e.target.value = value;
        if (value.length === 1 && index < otp.length - 1) {
            otp[index + 1].focus();
        }
    });

    field.addEventListener('keydown', (e) => {
        if (e.key === 'Backspace' && e.target.value === '' && index > 0) {
            otp[index - 1].focus();
        }
    });

    field.addEventListener('paste', (e) => {
        e.preventDefault();
        const paste = (e.clipboardData || window.clipboardData).getData('text');
        const pasteArray = paste.split('').filter(char => /\d/.test(char));
        pasteArray.forEach((digit, i) => {
            if (index + i < otp.length) {
                otp[index + i].value = digit;
            }
        });
        const nextIndex = Math.min(index + pasteArray.length, otp.length - 1);
        otp[nextIndex].focus();
    });
});
