document.addEventListener("DOMContentLoaded", function () {

    const button = document.getElementById("quickActionBtn");
    const dropdown = document.getElementById("quickActionDropdown");

    button.addEventListener("click", function (e) {

        e.stopPropagation();

        dropdown.classList.toggle("show");

    });

    document.addEventListener("click", function () {

        dropdown.classList.remove("show");

    });

    dropdown.addEventListener("click", function (e) {

        e.stopPropagation();

    });

});
