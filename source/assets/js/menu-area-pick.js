// inspired by https://github.com/JanMikes/tomasvotruba.cz/blob/be9da66c3402adfe7928c3798ab1ccd6527f92cd/source/assets/js/checklist.js

$(function() {
    var $active_area = window.localStorage.getItem('active_area');
    var $menu_items = $('#area-menu li a');

    // active area found in localStorage
    // use "data-key=VALUE", see https://stackoverflow.com/a/39294578/1348344
    $menu_items.each(function() {
        if ($(this).data('key') === $active_area) {
            $(this).parent().addClass("active");
        }
    });

    // load only default active area items
    function showRowsFromArea(area) {
        $("tr[class^='meetup-with-area-']").hide();
        $("tr.meetup-with-area-" + area).show();
    }

    showRowsFromArea($active_area);

    $menu_items.click(function () {
        // change classes
        $("#area-menu li").removeClass("active");
        $(this).parent().addClass("active");

        // store
        window.localStorage.setItem('active_area', $(this).data('key'));

        showRowsFromArea($(this).data("key"));
    });
});
