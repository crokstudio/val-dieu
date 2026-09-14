// Use Brussels calendar days, regardless of the visitor's time zone or DST.
export function minimumVisitDate(now = new Date()) {
    const parts = Object.fromEntries(new Intl.DateTimeFormat('en-GB', {
        timeZone: 'Europe/Brussels', year: 'numeric', month: '2-digit', day: '2-digit',
    }).formatToParts(now).map(({ type, value }) => [type, value]));
    const date = new Date(Date.UTC(Number(parts.year), Number(parts.month) - 1, Number(parts.day) + 21));
    return date.toISOString().slice(0, 10);
}

const form = document.querySelector('.guided-visits--form');
if (form) {
    const dateInput = form.elements.visit_date;
    const counts = ['adult_count', 'teen_count', 'child_count'].map(name => form.elements[name]);
    const refreshDate = () => { dateInput.min = minimumVisitDate(); };
    const validateGroup = () => {
        const total = counts.reduce((sum, input) => sum + (Number(input.value) || 0), 0);
        counts[0].setCustomValidity(total < 15 ? form.dataset.groupError : '');
    };
    refreshDate();
    validateGroup();
    counts.forEach(input => input.addEventListener('input', validateGroup));
    dateInput.addEventListener('focus', refreshDate);
    window.addEventListener('pageshow', () => { refreshDate(); validateGroup(); });
    // Refresh before native submit validation, including pages kept open overnight.
    form.querySelector('button[type="submit"]').addEventListener('click', refreshDate);
    form.addEventListener('submit', event => {
        refreshDate();
        validateGroup();
        if (!form.reportValidity()) event.preventDefault();
    });
}
