if (jQuery) {
    jQuery(document).ready(function ($) {
        $('.mod_wow_raid_progress_wotlk .header').click(function () {
            if ($(this).next('li').is(':visible')) {
                $(this).next('li').slideUp('slow');
            } else {
                $('.mod_wow_raid_progress_wotlk .npcs').slideUp('slow');
                $(this).next('li').slideToggle('slow');
            }
        });
    });
}