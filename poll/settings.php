<?php


defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    
    $settings->add(new admin_setting_heading('block_poll_general', 
        get_string('general', 'admin'), 
        get_string('general', 'admin')));
    
    // Maximum options per poll
    $settings->add(new admin_setting_configtext('block_poll_maxoptions', 
        get_string('maxoptions', 'block_poll'), 
        get_string('maxoptions_desc', 'block_poll'), 
        10, PARAM_INT));
    
    // Allow multiple votes per user
    $settings->add(new admin_setting_configcheckbox('block_poll_allowmultiple', 
        get_string('allowmultiple', 'block_poll'), 
        get_string('allowmultiple_desc', 'block_poll'), 
        0));
}



