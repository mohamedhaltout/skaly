document.addEventListener('DOMContentLoaded', () => {
    let scrollRightBtn = document.getElementById('scrollRight');
    let categoriesSlider = document.querySelector('.categories-scroll-slider');

    scrollRightBtn.addEventListener('click', () => {
        categoriesSlider.scrollBy({ left: 200, behavior: 'smooth' });
    });

    function renderStars(container) {
        let rating = parseFloat(container.dataset.rating);
        let totalStars = 5;
        container.innerHTML = '';

        for (let i = 1; i <= totalStars; i++) {
            let starImg = document.createElement('img');
            starImg.classList.add('review-star-icon');
            if (i <= rating) {
                starImg.src = 'img/review_group.svg';
                starImg.alt = 'Star';
            } else {
                starImg.src = 'img/empty_star.svg';
                starImg.alt = 'Empty Star';
            }
            container.appendChild(starImg);
        }
    }

    document.querySelectorAll('.star-rating-display').forEach(renderStars);

    let mainGalleryImage = document.querySelector('.main-gallery-image');
    let thumbnails = document.querySelectorAll('.gallery-thumbnail');
    let prevArrow = document.querySelector('.arrow-left');
    let nextArrow = document.querySelector('.arrow-right');

    let currentMediaIndex = 0;
    let mediaSources = Array.from(thumbnails).map(thumb => thumb.src);

    function updateMainMedia(index) {
        if (index >= 0 && index < mediaSources.length) {
            mainGalleryImage.src = mediaSources[index];
            thumbnails.forEach((thumb, idx) => {
                thumb.classList.toggle('active', idx === index);
            });
            currentMediaIndex = index;
        }
    }

    thumbnails.forEach((thumbnail, index) => {
        thumbnail.addEventListener('click', () => updateMainMedia(index));
    });

    prevArrow.addEventListener('click', () => {
        let newIndex = (currentMediaIndex - 1 + mediaSources.length) % mediaSources.length;
        updateMainMedia(newIndex);
    });

    nextArrow.addEventListener('click', () => {
        let newIndex = (currentMediaIndex + 1) % mediaSources.length;
        updateMainMedia(newIndex);
    });

    if (mediaSources.length > 0) {
        updateMainMedia(0);
    }

    let calendarGrid = document.querySelector('.calendar-grid');
    let monthYearDisplay = document.querySelector('.calendar-month-year');
    let prevMonthArrow = document.querySelector('.prev-month');
    let nextMonthArrow = document.querySelector('.next-month');
    let currentDate = new Date();
    let selectedDate = null; // To store the selected date
    // artisanId is now a global variable defined in artisan.php
    const requestServiceBtn = document.getElementById('requestServiceBtn'); // Get the button by its ID

    function renderCalendar() {
        calendarGrid.innerHTML = '';
        monthYearDisplay.textContent = currentDate.toLocaleString('en-US', { month: 'long', year: 'numeric' });

        let firstDayOfMonth = new Date(currentDate.getFullYear(), currentDate.getMonth(), 1);
        let lastDayOfMonth = new Date(currentDate.getFullYear(), currentDate.getMonth() + 1, 0);
        let lastDayOfPrevMonth = new Date(currentDate.getFullYear(), currentDate.getMonth(), 0);
        let startDayIndex = firstDayOfMonth.getDay();
        let today = new Date();
        today.setHours(0, 0, 0, 0);

        for (let i = startDayIndex; i > 0; i--) {
            let span = document.createElement('span');
            span.classList.add('calendar-date', 'inactive');
            span.textContent = lastDayOfPrevMonth.getDate() - i + 1;
            calendarGrid.appendChild(span);
        }

        for (let i = 1; i <= lastDayOfMonth.getDate(); i++) {
            let span = document.createElement('span');
            span.classList.add('calendar-date');
            span.textContent = i;

            let fullDate = new Date(currentDate.getFullYear(), currentDate.getMonth(), i);
            let dateStr = fullDate.toDateString();

            if (fullDate < today) {
                span.classList.add('past-date');
                span.style.cursor = 'not-allowed';
            } else if (unavailableDates.includes(dateStr)) {
                span.classList.add('unavailable');
                span.style.cursor = 'not-allowed';
            } else {
                span.classList.add('available');
                span.addEventListener('click', () => {
                    // Remove 'selected' from previously selected date
                    document.querySelectorAll('.calendar-date.selected').forEach(el => {
                        el.classList.remove('selected');
                    });
                    // Add 'selected' to current date
                    span.classList.add('selected');
                    selectedDate = dateStr; // Store the selected date
                    if (requestServiceBtn) {
                        requestServiceBtn.disabled = false; // Enable the button
                    }
                });
            }
            calendarGrid.appendChild(span);
        }

        let totalCells = startDayIndex + lastDayOfMonth.getDate();
        if (totalCells % 7 !== 0) {
            for (let i = 1; i <= (7 - (totalCells % 7)); i++) {
                let span = document.createElement('span');
                span.classList.add('calendar-date', 'inactive');
                span.textContent = i;
                calendarGrid.appendChild(span);
            }
        }
    }

    prevMonthArrow.addEventListener('click', () => {
        currentDate.setMonth(currentDate.getMonth() - 1);
        selectedDate = null; // Reset selected date on month change
        updateRequestServiceButtonState();
        renderCalendar();
    });

    nextMonthArrow.addEventListener('click', () => {
        currentDate.setMonth(currentDate.getMonth() + 1);
        selectedDate = null; // Reset selected date on month change
        updateRequestServiceButtonState();
        renderCalendar();
    });

    function updateRequestServiceButtonState() {
        if (requestServiceBtn) {
            // If a date is selected AND the button is not globally disabled by PHP
            if (selectedDate && !requestServiceBtn.classList.contains('unavailable-btn')) {
                requestServiceBtn.disabled = false;
            } else {
                requestServiceBtn.disabled = true;
            }
        }
    }

    if (requestServiceBtn) {
        requestServiceBtn.addEventListener('click', (event) => {
            event.preventDefault(); // Prevent default form submission
            if (selectedDate && typeof artisanId !== 'undefined') { // Check if artisanId is defined globally
                window.location.href = `demande_service.php?id_prestataire=${artisanId}&date_dispo=${selectedDate}`;
            } else {
                alert('Please select an available date first.');
            }
        });
    }
    renderCalendar();
    updateRequestServiceButtonState(); // Initial state of the button

    renderCalendar();
});
