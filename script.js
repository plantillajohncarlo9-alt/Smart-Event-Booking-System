document.addEventListener("DOMContentLoaded", function () {

    // LOGIN AND REGISTER TABS

    const panels = document.querySelectorAll(".auth-form");
    const tabs = document.querySelectorAll(".tab-button");

    function showPanel(id) {

        panels.forEach(function (panel) {
            panel.classList.toggle("show", panel.id === id);
        });

        tabs.forEach(function (tab) {
            tab.classList.toggle(
                "active",
                tab.dataset.target === id
            );
        });
    }

    tabs.forEach(function (tab) {
        tab.addEventListener("click", function () {
            showPanel(tab.dataset.target);
        });
    });

    document.querySelectorAll("[data-show]").forEach(function (button) {
        button.addEventListener("click", function () {
            showPanel(button.dataset.show);
        });
    });


    // BOOKING FORM VALIDATION

    const bookingForm = document.querySelector("[data-booking-form]");

    if (bookingForm) {

        bookingForm.addEventListener("submit", function (event) {

            const dateInput = bookingForm.querySelector('[name="event_date"]');
            const guestsInput = bookingForm.querySelector('[name="guest_count"]');

            const today = new Date();
            today.setHours(0, 0, 0, 0);

            const selectedDate = new Date(dateInput.value + "T00:00:00");

            if (!dateInput.value || selectedDate < today) {
                event.preventDefault();
                alert("Please choose today or a future event date.");
                return;
            }

            if (!guestsInput.value || Number(guestsInput.value) < 1) {
                event.preventDefault();
                alert("Guest count must be at least 1.");
            }

        });

    }

});