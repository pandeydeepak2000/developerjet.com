import './bootstrap';

const menuToggle = document.querySelector('[data-menu-toggle]');
const menu = document.querySelector('[data-menu]');

if (menuToggle && menu) {
    menuToggle.addEventListener('click', () => {
        const isOpen = menuToggle.getAttribute('aria-expanded') === 'true';

        menuToggle.setAttribute('aria-expanded', String(!isOpen));
        menu.classList.toggle('is-open', !isOpen);
    });

    menu.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', () => {
            menuToggle.setAttribute('aria-expanded', 'false');
            menu.classList.remove('is-open');
        });
    });
}

const hermesDropdown = document.querySelector('[data-hermes-dropdown]');
const hermesDropdownToggle = hermesDropdown?.querySelector('[data-hermes-dropdown-toggle]');

if (hermesDropdown && hermesDropdownToggle) {
    hermesDropdownToggle.addEventListener('click', () => {
        const isOpen = hermesDropdownToggle.getAttribute('aria-expanded') === 'true';

        hermesDropdownToggle.setAttribute('aria-expanded', String(!isOpen));
        hermesDropdown.classList.toggle('is-open', !isOpen);
    });

    document.addEventListener('click', (event) => {
        if (event.target instanceof Node && !hermesDropdown.contains(event.target)) {
            hermesDropdownToggle.setAttribute('aria-expanded', 'false');
            hermesDropdown.classList.remove('is-open');
        }
    });

    hermesDropdown.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            hermesDropdownToggle.setAttribute('aria-expanded', 'false');
            hermesDropdown.classList.remove('is-open');
            hermesDropdownToggle.focus();
        }
    });
}

const billingButtons = document.querySelectorAll('[data-billing-toggle]');

billingButtons.forEach((button) => {
    button.addEventListener('click', () => {
        const billing = button.dataset.billingToggle;

        billingButtons.forEach((option) => {
            const isActive = option === button;

            option.classList.toggle('is-active', isActive);
            option.setAttribute('aria-pressed', String(isActive));
        });

        document.querySelectorAll('[data-plan-card]').forEach((card) => {
            const price = card.querySelector('[data-monthly]');
            const billingNote = card.querySelector('[data-billing-note]');
            const annualTotal = card.querySelector('[data-annual-total-note]');
            const annualSavings = card.querySelector('[data-annual-savings-note]');
            const monthlyCompare = card.querySelector('[data-monthly-compare]');

            if (!price || !billingNote || !annualTotal || !annualSavings || !monthlyCompare) {
                return;
            }

            if (billing === 'annual') {
                price.textContent = price.dataset.annual;
                billingNote.textContent = 'Billed for 12 months upfront';
                annualTotal.hidden = false;
                annualSavings.hidden = false;
                monthlyCompare.hidden = false;
            } else {
                price.textContent = price.dataset.monthly;
                billingNote.textContent = 'Billed monthly';
                annualTotal.hidden = true;
                annualSavings.hidden = true;
                monthlyCompare.hidden = true;
            }
        });
    });
});
