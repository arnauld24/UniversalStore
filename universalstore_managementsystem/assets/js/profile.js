    //submenu dropdown function
        // Elements
        const accountButton = document.getElementById('navbarAccountDropdown');
        const accountMenu = document.querySelector('.dropdown-menu[aria-labelledby="navbarAccountDropdown"]');
        const submenuLinks = accountMenu ? accountMenu.querySelectorAll('[data-tab]') : [];
        const tabLinks = document.querySelectorAll('.tab-link');
        const tabContents = document.querySelectorAll('.tab-content-panel');
        const menuToggle = document.getElementById('menu-toggle');
        const mobileMenu = document.getElementById('mobile-menu');
        // Desktop nav container uses Tailwind `md:flex` class; select it safely
        const desktopNav = document.querySelector('nav .md\\:flex');

        const MD_BREAKPOINT = 768; // px - tailwind md

        // Accessibility helper
        function setAriaExpanded(el, state) {
            if (el) el.setAttribute('aria-expanded', state ? 'true' : 'false');
        }

        // Account dropdown toggle
        function toggleAccountMenu(open = null) {
            if (!accountMenu || !accountButton) return;
            const isOpen = accountMenu.classList.contains('opacity-100');
            const shouldOpen = open === null ? !isOpen : !!open;
            if (shouldOpen) {
                accountMenu.classList.remove('invisible', 'opacity-0', 'scale-95');
                accountMenu.classList.add('opacity-100', 'scale-100');
                setAriaExpanded(accountButton, true);
            } else {
                accountMenu.classList.add('invisible', 'opacity-0', 'scale-95');
                accountMenu.classList.remove('opacity-100', 'scale-100');
                setAriaExpanded(accountButton, false);
            }
        }

        if (accountButton) accountButton.addEventListener('click', (e) => { e.stopPropagation(); toggleAccountMenu(); });

        // Close account menu on outside click
        document.addEventListener('click', (e) => {
            if (accountMenu && accountButton && !accountMenu.contains(e.target) && !accountButton.contains(e.target)) {
                toggleAccountMenu(false);
            }
        });

        // Tab activation
        function activateTab(tabName, pushHistory = true) {
            if (!tabName) return;
            const name = String(tabName).toLowerCase();

            // Hide all panels
            tabContents.forEach(panel => panel.classList.add('hidden'));

            // Show target panel
            const target = document.getElementById(name);
            if (target) target.classList.remove('hidden');

            // Update tab link active state
            tabLinks.forEach(link => {
                const targetId = link.dataset.tabTarget || link.getAttribute('data-tab-target') || link.getAttribute('data-tab');
                if (targetId && String(targetId).toLowerCase() === name) {
                    link.classList.add('active-tab', 'text-gray-900', 'border-b-2', 'border-gray-900', 'font-semibold');
                    link.classList.remove('text-gray-500');
                } else {
                    link.classList.remove('active-tab', 'text-gray-900', 'border-b-2', 'border-gray-900', 'font-semibold');
                    link.classList.add('text-gray-500');
                }
            });

            // Close account dropdown if open
            toggleAccountMenu(false);

            // Update URL and history
            const base = window.location.pathname;
            const newUrl = `${base}?tab=${encodeURIComponent(name)}`;
            if (pushHistory) history.pushState({ tab: name }, '', newUrl);
        }

        // Hook clicks on main tab links
        tabLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const target = link.dataset.tabTarget || link.getAttribute('data-tab-target') || link.getAttribute('data-tab');
                if (target) activateTab(target, true);
            });
        });

        // Hook clicks on account submenu links (they should have data-tab attribute)
        submenuLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const tab = link.getAttribute('data-tab');
                if (tab) {
                    activateTab(tab, true);
                }
            });
        });

        // Popstate handling (back/forward)
        window.addEventListener('popstate', (event) => {
            const tab = event.state && event.state.tab ? event.state.tab : (new URLSearchParams(window.location.search)).get('tab') || 'profil';
            activateTab(tab, false);
        });

        // Initial activation on load
        document.addEventListener('DOMContentLoaded', () => {
            const urlTab = (new URLSearchParams(window.location.search)).get('tab');
            const initialTab = urlTab || (document.querySelector('.tab-link.active-tab')?.dataset.tabTarget) || 'profil';
            // Replace state so popstate works predictably
            history.replaceState({ tab: initialTab }, '', `${window.location.pathname}${initialTab === 'profil' ? '' : `?tab=${encodeURIComponent(initialTab)}`}`);
            activateTab(initialTab, false);
            // Initial sync of mobile menu
            syncMobileMenu();
        });

        // Mobile menu sync: copy desktop nav links into mobile menu (exclude cart and account)
        function syncMobileMenu() {
            if (!mobileMenu || !desktopNav) return;
            const isMobile = window.innerWidth < MD_BREAKPOINT;

            // Remove previous clones
            mobileMenu.querySelectorAll('.mobile-clone').forEach(n => n.remove());

            if (!isMobile) {
                // hide mobile menu when desktop
                mobileMenu.classList.add('hidden');
                return;
            }

            // Clone desktop nav links into mobile menu
            const links = desktopNav.querySelectorAll('a');
            links.forEach(a => {
                const href = a.getAttribute('href') || '';
                // Skip cart link and any account-specific link (account is a button, not an anchor)
                if (href.includes('cart.php') || href.includes('account') ) return;
                const clone = a.cloneNode(true);
                clone.classList.add('block', 'py-1', 'mobile-clone');
                mobileMenu.appendChild(clone);
            });
        }

        // Toggle mobile menu button
        if (menuToggle && mobileMenu) {
            menuToggle.addEventListener('click', (e) => {
                e.stopPropagation();
                mobileMenu.classList.toggle('hidden');
            });
        }

        // Keep mobile menu in sync on resize (debounced)
        let resizeTimer = null;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(syncMobileMenu, 120);
        });

        // Close mobile menu when clicking outside
        document.addEventListener('click', (e) => {
            if (mobileMenu && !mobileMenu.contains(e.target) && menuToggle && !menuToggle.contains(e.target)) {
                mobileMenu.classList.add('hidden');
            }
        });

//NAV BAR 

const dropDownMenu = document.getElementById('dropdown-menu');
const toggleNavbar = document.querySelector('.toggle-navbar');
const dropDwonClose = document.querySelector('.dropdown-close');
const navMenu = document.querySelector('.nav-menu');
const menuItem = document.getElementById('menuItem');
const tabNav = document.querySelector('.max-w-7xl .nav');


dropDownMenu.previousElementSibling.addEventListener('click', function () {
     if(window.innerWidth < 768){
     dropDownMenu.classList.add('show');
     toggleNavbar.classList.add('hide');
     navMenu.classList.add('hidden');
     desktopNav.classList.add('hidden');
     }
})

dropDwonClose.addEventListener('click', function () {
     if(window.innerWidth < 768){
     dropDownMenu.classList.remove('show');
     toggleNavbar.classList.remove('hide');
     navMenu.classList.remove('hidden');
     desktopNav.classList.remove('hidden');
     }
})

toggleNavbar.addEventListener('click', function () {
     if(window.innerWidth < 768){
     navMenu.classList.toggle('show');
      document.body.classList.add('nav-open');
     
     if(navMenu.classList.contains('show')){
          this.classList.replace('fa-bars', 'fa-xmark');
     }else {
        document.body.classList.remove('nav-open');
          this.classList.replace('fa-xmark','fa-bars');
     }
}
});
