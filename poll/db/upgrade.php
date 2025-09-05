<?php


defined('MOODLE_INTERNAL') || die();


function xmldb_block_poll_upgrade($oldversion) {
    global $CFG, $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2024120101) {
        // Upgrade to version 2024120101
        
        
        if ($dbman->table_exists('block_poll_polls')) {
            $table = new xmldb_table('block_poll_polls');
            
            
            $field = new xmldb_field('title', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null, 'id');
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
            
            $field = new xmldb_field('description', XMLDB_TYPE_TEXT, null, null, null, null, null, 'title');
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
            
            $field = new xmldb_field('poll_type', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'single', 'description');
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
            
            $field = new xmldb_field('poll_mode', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'text', 'poll_type');
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
            
            $field = new xmldb_field('start_time', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'poll_mode');
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
            
            $field = new xmldb_field('end_time', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'start_time');
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
            
            
            $sql = "UPDATE {block_poll_polls} SET title = question WHERE title IS NULL OR title = ''";
            $DB->execute($sql);
            
            
            $sql = "UPDATE {block_poll_polls} SET poll_type = 'single', poll_mode = 'text' WHERE poll_type IS NULL OR poll_mode IS NULL";
            $DB->execute($sql);
            
            
            $field = new xmldb_field('question');
            if ($dbman->field_exists($table, $field)) {
                $dbman->drop_field($table, $field);
            }
            
            
            $index = new xmldb_index('poll_type_idx', XMLDB_INDEX_NOTUNIQUE, array('poll_type'));
            if (!$dbman->index_exists($table, $index)) {
                $dbman->add_index($table, $index);
            }
            
            $index = new xmldb_index('poll_mode_idx', XMLDB_INDEX_NOTUNIQUE, array('poll_mode'));
            if (!$dbman->index_exists($table, $index)) {
                $dbman->add_index($table, $index);
            }
        }
        
        
        if ($dbman->table_exists('block_poll_options')) {
            $table = new xmldb_table('block_poll_options');
            
            
            $field = new xmldb_field('start_time', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'sort_order');
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
            
            $field = new xmldb_field('end_time', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'start_time');
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
    }

    if ($oldversion < 2024120102) {
        
        
        
        if ($dbman->table_exists('block_poll_polls')) {
            $table = new xmldb_table('block_poll_polls');
            
            
            $sql = "UPDATE {block_poll_polls} SET poll_type = 'single' WHERE poll_type IS NULL OR poll_type = ''";
            $DB->execute($sql);
            
            
            $sql = "UPDATE {block_poll_polls} SET poll_mode = 'text' WHERE poll_mode IS NULL OR poll_mode = ''";
            $DB->execute($sql);
        }
    }

    
    if ($oldversion < 2024120104) {
        $table = new xmldb_table('block_poll_votes');

        
        $olduq = new xmldb_key('unique_vote', XMLDB_KEY_UNIQUE, ['poll_id', 'user_id']);
        try { $dbman->drop_key($table, $olduq); } catch (Exception $e) { /* ignore */ }

        
        $newuq = new xmldb_key('unique_vote', XMLDB_KEY_UNIQUE, ['poll_id', 'user_id', 'option_id']);
        try { $dbman->add_key($table, $newuq); } catch (Exception $e) { /* ignore */ }
    }

    
    if ($oldversion < 2024120105) {
        
        if ($dbman->table_exists('block_poll_polls')) {
            $table = new xmldb_table('block_poll_polls');
            
            
            $field = new xmldb_field('time_created', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'created_by');
            if (!$dbman->field_exists($table, $field)) {
                
                $oldfield = new xmldb_field('timecreated');
                if ($dbman->field_exists($table, $oldfield)) {
                    $dbman->rename_field($table, $oldfield, 'time_created');
                } else {
                    
                    $dbman->add_field($table, $field);
                }
            }
        }
        
        if ($dbman->table_exists('block_poll_defense_settings')) {
            $table = new xmldb_table('block_poll_defense_settings');
            
            
            $field = new xmldb_field('time_created', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'note');
            if (!$dbman->field_exists($table, $field)) {
                
                $oldfield = new xmldb_field('timecreated');
                if ($dbman->field_exists($table, $oldfield)) {
                    $dbman->rename_field($table, $oldfield, 'time_created');
                } else {
                    
                    $dbman->add_field($table, $field);
                }
            }
        }
    }

    return true;
}
