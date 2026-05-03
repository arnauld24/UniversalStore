const toggleNavbar = document.querySelector('.toggle-navbar');
const navMenu = document.querySelector('.nav-menu');

// Open a category dropdown on small screens
function closeAllDropdowns() {
    document.querySelectorAll('.nav-menu > li .dropdown-menu.show').forEach(drop => {
        drop.classList.remove('show');
    });
}

document.querySelectorAll('.nav-menu > li > a.bottom').forEach(categoryLink => {
    categoryLink.addEventListener('click', function (event) {
        if (window.innerWidth < 768) {
            event.preventDefault();
            const iconInside = this.querySelector('.hidden-arrow');
            const iconSibling = this.nextElementSibling && this.nextElementSibling.classList.contains('hidden-arrow') ? this.nextElementSibling : null;
            const icon = iconInside || iconSibling;

            let dropdown = null;
            if (icon && icon.nextElementSibling && icon.nextElementSibling.classList.contains('dropdown-menu')) {
                dropdown = icon.nextElementSibling;
            } else if (this.nextElementSibling && this.nextElementSibling.classList.contains('dropdown-menu')) {
                dropdown = this.nextElementSibling;
            }

            if (dropdown) {
                if (dropdown.classList.contains('show')) {
                    dropdown.classList.remove('show');
                    if (icon && icon.classList.contains('fa-chevron-down')) {
                        icon.classList.replace('fa-chevron-down', 'fa-chevron-right');
                    }
                } else {
                    closeAllDropdowns();
                    dropdown.classList.add('show');
                    if (icon && icon.classList.contains('fa-chevron-right')) {
                        icon.classList.replace('fa-chevron-right', 'fa-chevron-down');
                    }
                    navMenu.classList.add('hidden');
                    toggleNavbar.classList.add('hide');
                }
            }
        }
    });
});

// Close button in dropdown
document.querySelectorAll('.dropdown-close').forEach(closeBtn => {
    closeBtn.addEventListener('click', function () {
        if (window.innerWidth < 768) {
            const dropdown = this.closest('.dropdown-menu');
            if (dropdown) {
                dropdown.classList.remove('show');
            }
            document.querySelectorAll('.nav-menu > li > i.fas').forEach(icon => {
                icon.classList.remove('fa-chevron-down');
                icon.classList.add('fa-chevron-right');
            });
            navMenu.classList.remove('hidden');
            toggleNavbar.classList.remove('hide');
        }
    });
});

// Toggle top-level mobile nav
toggleNavbar.addEventListener('click', function () {
    if (window.innerWidth < 768) {
        navMenu.classList.toggle('show');
        if (navMenu.classList.contains('show')) {
            this.classList.replace('fa-bars', 'fa-xmark');
        } else {
            this.classList.replace('fa-xmark', 'fa-bars');
            closeAllDropdowns();
            document.querySelectorAll('.nav-menu > li > i.fas').forEach(icon => {
                icon.classList.remove('fa-chevron-down');
                icon.classList.add('fa-chevron-right');
            });
        }
    }
});
