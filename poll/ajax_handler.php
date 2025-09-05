<?php

require_once('../../config.php');
require_once($CFG->libdir.'/adminlib.php');

require_login();
require_sesskey();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!isset($_POST['action'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No action specified']);
    exit;
}

switch ($_POST['action']) {
    case 'create_poll':
        handle_create_poll();
        break;
        
    case 'delete_poll':
        handle_delete_poll();
        break;
        
    case 'get_poll_statistics':
        handle_get_poll_statistics();
        break;
        
            case 'get_professor_details':
            handle_get_professor_details();
            break;
            
        case 'get_poll_results':
            handle_get_poll_results();
            break;
            
        case 'submit_vote':
            handle_submit_vote();
            break;
        case 'submit_multiple_choice_vote':
            handle_submit_multiple_choice_vote();
            break;
        

        
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
        exit;
}

/**
 * Handle poll creation
 */
function handle_create_poll() {
    global $DB, $USER;
    
    try {
        error_log('Poll creation attempt - POST data: ' . print_r($_POST, true));
        error_log('Poll type received: ' . ($_POST['poll_type'] ?? 'NULL'));
        error_log('Poll mode received: ' . ($_POST['poll_mode'] ?? 'NULL'));
        
        if (empty($_POST['title']) || empty($_POST['poll_type']) || empty($_POST['poll_mode'])) {
            throw new Exception('Missing required fields: title=' . ($_POST['title'] ?? 'empty') . ', type=' . ($_POST['poll_type'] ?? 'empty') . ', mode=' . ($_POST['poll_mode'] ?? 'empty'));
        }
        
        $context = context_system::instance();
        if (!has_capability('moodle/site:config', $context) && $USER->username !== 'admin' && $USER->id != 1) {
            throw new Exception('Insufficient permissions - User: ' . $USER->username . ', ID: ' . $USER->id);
        }
        
        $tables = $DB->get_tables();
        if (!in_array('block_poll_polls', $tables)) {
            throw new Exception('Database table block_poll_polls does not exist. Available tables: ' . implode(', ', $tables));
        }
        
        $poll_data = new stdClass();
        $poll_data->title = trim($_POST['title']);
        $poll_data->description = !empty($_POST['description']) ? trim($_POST['description']) : '';
        $poll_data->poll_type = $_POST['poll_type'];
        $poll_data->poll_mode = $_POST['poll_mode'];
        $poll_data->created_by = $USER->id;
        $poll_data->time_created = time();
        $poll_data->active = 1;
        
        if (!empty($_POST['start_time'])) {
            $poll_data->start_time = (int)$_POST['start_time'];
        }
        if (!empty($_POST['end_time'])) {
            $poll_data->end_time = (int)$_POST['end_time'];
        }
        
        error_log('Poll data prepared: ' . print_r($poll_data, true));
        
        $poll_id = $DB->insert_record('block_poll_polls', $poll_data);
        
        if (!$poll_id) {
            throw new Exception('Failed to create poll - Database error: ' . $DB->get_last_error());
        }
        
        error_log('Poll created with ID: ' . $poll_id);
        
        switch ($_POST['poll_mode']) {
            case 'text':
                handle_text_poll_options($DB, $poll_id, $_POST);
                break;
                
            case 'time':
                handle_time_poll_options($DB, $poll_id, $_POST);
                break;
                
            case 'custom_timeslot':
                handle_custom_timeslot_options($DB, $poll_id, $_POST);
                break;
        }
        
        header('Content-Type: application/json');
        header('Cache-Control: no-cache, must-revalidate');
        
        $response = ['success' => true, 'message' => 'Poll created successfully', 'poll_id' => $poll_id];
        echo json_encode($response);
        error_log('Sending success response: ' . json_encode($response));
        
    } catch (Exception $e) {
        error_log('Poll creation error: ' . $e->getMessage());
        header('Content-Type: application/json');
        header('Cache-Control: no-cache, must-revalidate');
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Handle poll deletion - PERMANENT DELETION
 * This function completely removes the poll and all related data from the database.
 * Deleted polls cannot be recovered and will not be visible to professors.
 */
function handle_delete_poll() {
    global $DB, $USER;
    
    try {
        if (empty($_POST['poll_id'])) {
            throw new Exception('Poll ID is required');
        }
        
        $poll_id = (int)$_POST['poll_id'];
        
        $context = context_system::instance();
        if (!has_capability('moodle/site:config', $context) && $USER->username !== 'admin' && $USER->id != 1) {
            throw new Exception('Insufficient permissions to delete polls');
        }
        
        $poll = $DB->get_record('block_poll_polls', array('id' => $poll_id));
        if (!$poll) {
            throw new Exception('Poll not found');
        }
        
        $transaction = $DB->start_delegated_transaction();
        
        try {
            $DB->delete_records('block_poll_defense_settings', array('poll_id' => $poll_id));
            
            $DB->delete_records('block_poll_options', array('poll_id' => $poll_id));
            
            $DB->delete_records('block_poll_votes', array('poll_id' => $poll_id));
            
            $DB->delete_records('block_poll_polls', array('id' => $poll_id));
            
            $remaining_poll = $DB->get_record('block_poll_polls', array('id' => $poll_id));
            if ($remaining_poll) {
                throw new Exception('Failed to delete poll - poll still exists after deletion');
            }
            
            $remaining_options = $DB->count_records('block_poll_options', array('poll_id' => $poll_id));
            $remaining_votes = $DB->count_records('block_poll_votes', array('poll_id' => $poll_id));
            $remaining_defense = $DB->count_records('block_poll_defense_settings', array('poll_id' => $poll_id));
            
            if ($remaining_options > 0 || $remaining_votes > 0 || $remaining_defense > 0) {
                throw new Exception('Failed to delete all related records - some data still exists');
            }
            
            $transaction->allow_commit();
            
            error_log("Poll {$poll_id} and all related data deleted successfully by user {$USER->id}");
            
        } catch (Exception $e) {
            $transaction->rollback($e);
            error_log("Poll deletion failed for poll {$poll_id}: " . $e->getMessage());
            throw $e;
        }
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Poll deleted successfully']);
        
    } catch (Exception $e) {
        error_log('Poll deletion error: ' . $e->getMessage());
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Handle bulk poll deletion (for future use)
 */
function handle_bulk_delete_polls() {
    global $DB, $USER;
    
    try {
        if (empty($_POST['poll_ids']) || !is_array($_POST['poll_ids'])) {
            throw new Exception('Poll IDs array is required');
        }
        
        $poll_ids = array_map('intval', $_POST['poll_ids']);
        
        $context = context_system::instance();
        if (!has_capability('moodle/site:config', $context) && $USER->username !== 'admin' && $USER->id != 1) {
            throw new Exception('Insufficient permissions to delete polls');
        }
        
        $deleted_count = 0;
        $errors = [];
        
        foreach ($poll_ids as $poll_id) {
            try {
                $poll = $DB->get_record('block_poll_polls', array('id' => $poll_id));
                if (!$poll) {
                    $errors[] = "Poll ID {$poll_id} not found";
                    continue;
                }
                
                $transaction = $DB->start_delegated_transaction();
                
                try {
                    $DB->delete_records('block_poll_defense_settings', array('poll_id' => $poll_id));
                    
                    $DB->delete_records('block_poll_options', array('poll_id' => $poll_id));
                    
                    $DB->delete_records('block_poll_votes', array('poll_id' => $poll_id));
                    
                    $DB->delete_records('block_poll_polls', array('id' => $poll_id));
                    
                    $transaction->allow_commit();
                    
                    $deleted_count++;
                    error_log("Poll {$poll_id} deleted successfully in bulk operation by user {$USER->id}");
                    
                } catch (Exception $e) {
                    $transaction->rollback($e);
                    $errors[] = "Failed to delete poll {$poll_id}: " . $e->getMessage();
                }
                
            } catch (Exception $e) {
                $errors[] = "Error processing poll {$poll_id}: " . $e->getMessage();
            }
        }
        
        $response = [
            'success' => true,
            'deleted_count' => $deleted_count,
            'total_requested' => count($poll_ids),
            'errors' => $errors
        ];
        
        if (empty($errors)) {
            $response['message'] = "Successfully deleted {$deleted_count} polls";
        } else {
            $response['message'] = "Deleted {$deleted_count} polls with some errors";
        }
        
        header('Content-Type: application/json');
        echo json_encode($response);
        
    } catch (Exception $e) {
        error_log('Bulk poll deletion error: ' . $e->getMessage());
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Get poll statistics for Non-Voting Professors section
 */
function handle_get_poll_statistics() {
    global $DB, $USER;
    
    try {
        $context = context_system::instance();
        if (!has_capability('moodle/site:config', $context) && $USER->username !== 'admin' && $USER->id != 1) {
            throw new Exception('Insufficient permissions to view statistics');
        }
        
        $all_roles = $DB->get_records('role', array(), 'shortname');
        error_log('Available roles: ' . implode(', ', array_keys($all_roles)));
        
        $current_user_roles = $DB->get_records_sql("
            SELECT r.shortname, r.name 
            FROM {role} r 
            INNER JOIN {role_assignments} ra ON r.id = ra.roleid 
            WHERE ra.userid = ?", array($USER->id));
        error_log('Current user roles: ' . json_encode($current_user_roles));
        
        $professor_sql = "SELECT DISTINCT u.id, u.username, u.firstname, u.lastname
                          FROM {user} u
                          INNER JOIN {role_assignments} ra ON u.id = ra.userid
                          INNER JOIN {role} r ON ra.roleid = r.id
                          WHERE u.deleted = 0 AND u.suspended = 0
                          AND (r.shortname IN ('editingteacher', 'teacher', 'teacherrole', 'faculty', 'professor')
                               OR r.name LIKE '%teacher%'
                               OR r.name LIKE '%professor%'
                               OR r.name LIKE '%faculty%')
                          AND r.shortname NOT IN ('manager', 'admin', 'administrator', 'siteadmin', 'manager', 'developer')
                          AND r.name NOT LIKE '%manager%'
                          AND r.name NOT LIKE '%admin%'
                          AND r.name NOT LIKE '%administrator%'
                          AND r.name NOT LIKE '%developer%'
                          AND r.name NOT LIKE '%moodle%'
                          AND u.username NOT IN ('admin', 'moodle', 'developer')
                          AND u.id != 1
                          AND u.id != 2
                          ORDER BY u.id";
        
        $professors = $DB->get_records_sql($professor_sql);
        $total_professors = count($professors);
        
        error_log('Professor count: ' . $total_professors);
        if ($total_professors > 0) {
            foreach ($professors as $prof) {
                error_log('Professor: ' . $prof->username . ' (' . $prof->firstname . ' ' . $prof->lastname . ')');
            }
        }
        
        $current_user_is_professor = false;
        foreach ($professors as $prof) {
            if ($prof->id == $USER->id) {
                $current_user_is_professor = true;
                error_log('WARNING: Current user (' . $USER->username . ') is being counted as a professor!');
                break;
            }
        }
        if (!$current_user_is_professor) {
            error_log('Current user (' . $USER->username . ') is NOT counted as a professor - this is correct for managers');
        }
        
        
        $polls = $DB->get_records_sql("SELECT id, title, description, poll_type, poll_mode, created_by, time_created, active FROM {block_poll_polls} WHERE active = 1 ORDER BY id DESC");
        
        error_log('Found ' . count($polls) . ' active polls');
        if ($polls) {
            foreach ($polls as $poll) {
                error_log('Poll: ID=' . $poll->id . ', Title=' . $poll->title . ', Type=' . $poll->poll_type . ', Mode=' . $poll->poll_mode);
            }
        }
        
        if (!$polls) {
            $response = [
                'success' => true,
                'polls' => [],
                'overall_stats' => [
                    'total_polls' => 0,
                    'total_votes' => 0,
                    'total_not_voted' => 0,
                    'average_participation' => 0
                ]
            ];
        } else {
            $poll_data = [];
            $total_votes = 0;
            $total_polls = count($polls);
            
            foreach ($polls as $poll) {
                $voted_count = 0;
                if ($total_professors > 0) {
                    $voted_sql = "SELECT COUNT(DISTINCT v.user_id) as count
                                  FROM {block_poll_votes} v
                                  INNER JOIN {user} u ON v.user_id = u.id
                                  INNER JOIN {role_assignments} ra ON u.id = ra.userid
                                  INNER JOIN {role} r ON ra.roleid = r.id
                                  WHERE v.poll_id = ? 
                                  AND u.deleted = 0 AND u.suspended = 0
                                  AND (r.shortname IN ('editingteacher', 'teacher', 'teacherrole', 'faculty', 'professor')
                                       OR r.name LIKE '%teacher%'
                                       OR r.name LIKE '%professor%'
                                       OR r.name LIKE '%faculty%')
                                  AND r.shortname NOT IN ('manager', 'admin', 'administrator', 'siteadmin', 'manager', 'developer')
                                  AND r.name NOT LIKE '%manager%'
                                  AND r.name NOT LIKE '%admin%'
                                  AND r.name NOT LIKE '%administrator%'
                                  AND r.name NOT LIKE '%developer%'
                                  AND r.name NOT LIKE '%moodle%'
                                  AND u.username NOT IN ('admin', 'moodle', 'developer')
                                  AND u.id != 1
                                  AND u.id != 2";
                    $voted_result = $DB->get_record_sql($voted_sql, array($poll->id));
                    $voted_count = $voted_result ? $voted_result->count : 0;
                }
                
                $not_voted = $total_professors - $voted_count;
                $participation_rate = $total_professors > 0 ? round(($voted_count / $total_professors) * 100) : 0;
                
                $poll_data[] = [
                    'id' => $poll->id,
                    'title' => $poll->title,
                    'poll_type' => $poll->poll_type,
                    'poll_mode' => $poll->poll_mode,
                    'voted_count' => $voted_count,
                    'not_voted_count' => $not_voted,
                    'participation_rate' => $participation_rate,
                    'total_professors' => $total_professors
                ];
                
                $total_votes += $voted_count;
            }
            
            $overall_stats = [
                'total_polls' => $total_polls,
                'total_votes' => $total_votes,
                'total_not_voted' => ($total_polls * $total_professors) - $total_votes,
                'average_participation' => $total_polls > 0 ? round(array_sum(array_column($poll_data, 'participation_rate')) / $total_polls) : 0
            ];
            
            $response = [
                'success' => true,
                'polls' => $poll_data,
                'overall_stats' => $overall_stats
            ];
            
            error_log('Sending response with ' . count($poll_data) . ' polls');
            error_log('Response data: ' . json_encode($response));
            
            foreach ($poll_data as $poll) {
                error_log('Poll in response - ID: ' . $poll['id'] . ', Type: ' . $poll['poll_type'] . ', Mode: ' . $poll['poll_mode']);
            }
        }
        
        header('Content-Type: application/json');
        echo json_encode($response);
        
    } catch (Exception $e) {
        error_log('Poll statistics error: ' . $e->getMessage());
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Get detailed professor information for a specific poll
 */
function handle_get_professor_details() {
    global $DB, $USER;
    
    try {
        $context = context_system::instance();
        if (!has_capability('moodle/site:config', $context) && $USER->username !== 'admin' && $USER->id != 1) {
            throw new Exception('Insufficient permissions to view professor details');
        }
        
        if (empty($_POST['poll_id'])) {
            throw new Exception('Poll ID is required');
        }
        
        $poll_id = (int)$_POST['poll_id'];
        
        $poll = $DB->get_record('block_poll_polls', array('id' => $poll_id));
        if (!$poll) {
            throw new Exception('Poll not found');
        }
        
        $voted_sql = "SELECT DISTINCT u.id, u.firstname, u.lastname, u.email
                      FROM {user} u
                      INNER JOIN {block_poll_votes} v ON u.id = v.user_id
                      INNER JOIN {role_assignments} ra ON u.id = ra.userid
                      INNER JOIN {role} r ON ra.roleid = r.id
                      WHERE v.poll_id = ? 
                      AND u.deleted = 0 AND u.suspended = 0
                      AND (r.shortname IN ('editingteacher', 'teacher', 'teacherrole', 'faculty', 'professor')
                           OR r.name LIKE '%teacher%'
                           OR r.name LIKE '%professor%'
                           OR r.name LIKE '%faculty%')
                      AND r.shortname NOT IN ('manager', 'admin', 'administrator', 'siteadmin', 'manager', 'developer')
                      AND r.name NOT LIKE '%manager%'
                      AND r.name NOT LIKE '%admin%'
                      AND r.name NOT LIKE '%administrator%'
                      AND r.name NOT LIKE '%developer%'
                      AND r.name NOT LIKE '%moodle%'
                      AND u.username NOT IN ('admin', 'moodle', 'developer')
                      AND u.id != 1
                      AND u.id != 2
                      ORDER BY u.lastname, u.firstname";
        
        $voted_professors = $DB->get_records_sql($voted_sql, array($poll_id));
        
        $not_voted_sql = "SELECT u.id, u.firstname, u.lastname, u.email
                          FROM {user} u
                          INNER JOIN {role_assignments} ra ON u.id = ra.userid
                          INNER JOIN {role} r ON ra.roleid = r.id
                          WHERE u.deleted = 0 AND u.suspended = 0
                          AND (r.shortname IN ('editingteacher', 'teacher', 'teacherrole', 'faculty', 'professor')
                               OR r.name LIKE '%teacher%'
                               OR r.name LIKE '%professor%'
                               OR r.name LIKE '%faculty%')
                          AND r.shortname NOT IN ('manager', 'admin', 'administrator', 'siteadmin', 'manager', 'developer')
                          AND r.name NOT LIKE '%manager%'
                          AND r.name NOT LIKE '%admin%'
                          AND r.name NOT LIKE '%administrator%'
                          AND r.name NOT LIKE '%developer%'
                          AND r.name NOT LIKE '%moodle%'
                          AND u.username NOT IN ('admin', 'moodle', 'developer')
                          AND u.id != 1
                          AND u.id != 2
                          AND u.id NOT IN (
                              SELECT DISTINCT v.user_id 
                              FROM {block_poll_votes} v 
                              WHERE v.poll_id = ?
                          )
                          ORDER BY u.lastname, u.firstname";
        
        $not_voted_professors = $DB->get_records_sql($not_voted_sql, array($poll_id));
        
        $professor_sql = "SELECT COUNT(DISTINCT u.id) as count
                          FROM {user} u
                          INNER JOIN {role_assignments} ra ON u.id = ra.userid
                          INNER JOIN {role} r ON ra.roleid = r.id
                          WHERE u.deleted = 0 AND u.suspended = 0
                          AND (r.shortname IN ('editingteacher', 'teacher', 'teacherrole', 'faculty', 'professor')
                               OR r.name LIKE '%teacher%'
                               OR r.name LIKE '%professor%'
                               OR r.name LIKE '%faculty%')
                          AND r.shortname NOT IN ('manager', 'admin', 'administrator', 'siteadmin', 'manager', 'developer')
                          AND r.name NOT LIKE '%manager%'
                          AND r.name NOT LIKE '%admin%'
                          AND r.name NOT LIKE '%administrator%'
                          AND r.name NOT LIKE '%developer%'
                          AND r.name NOT LIKE '%moodle%'
                          AND u.username NOT IN ('admin', 'moodle', 'developer')
                          AND u.id != 1
                          AND u.id != 2";
        $professor_result = $DB->get_record_sql($professor_sql);
        $total_professors = $professor_result ? $professor_result->count : 0;
        
        $response = [
            'success' => true,
            'poll' => [
                'id' => $poll->id,
                'title' => $poll->title,
                'description' => $poll->description
            ],
            'voted_professors' => array_values($voted_professors),
            'not_voted_professors' => array_values($not_voted_professors),
            'total_professors' => $total_professors,
            'voted_count' => count($voted_professors),
            'not_voted_count' => count($not_voted_professors),
            'participation_rate' => $total_professors > 0 ? round((count($voted_professors) / $total_professors) * 100) : 0
        ];
        
        header('Content-Type: application/json');
        echo json_encode($response);
        
    } catch (Exception $e) {
        error_log('Professor details error: ' . $e->getMessage());
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**/ 

/**
 * Handle text poll options
 */
function handle_text_poll_options($DB, $poll_id, $post_data) {
    if (isset($post_data['options']) && is_array($post_data['options'])) {
        foreach ($post_data['options'] as $index => $option_text) {
            if (!empty(trim($option_text))) {
                $option = new stdClass();
                $option->poll_id = $poll_id;
                $option->option_text = trim($option_text);
                $option->sort_order = $index;
                $DB->insert_record('block_poll_options', $option);
            }
        }
    }
}

/**
 * Handle time poll options
 */
function handle_time_poll_options($DB, $poll_id, $post_data) {
    if (isset($post_data['timeslots']) && is_array($post_data['timeslots'])) {
        foreach ($post_data['timeslots'] as $index => $timeslot) {
            if (!empty($timeslot['start_time']) && !empty($timeslot['end_time'])) {
                $option = new stdClass();
                $option->poll_id = $poll_id;
                $start_time = date('M j g:i A', (int)$timeslot['start_time']);
                $end_time = date('M j g:i A', (int)$timeslot['end_time']);
                $option->option_text = $start_time . ' - ' . $end_time;
                $option->start_time = (int)$timeslot['start_time'];
                $option->end_time = (int)$timeslot['end_time'];
                $option->sort_order = $index;
                $DB->insert_record('block_poll_options', $option);
            }
        }
    }
    
    $defense_data = new stdClass();
    $defense_data->poll_id = $poll_id;
    $defense_data->defense_minutes = isset($post_data['defense_minutes']) ? (int)$post_data['defense_minutes'] : 20;
    $defense_data->buffer_minutes = isset($post_data['buffer_minutes']) ? (int)$post_data['buffer_minutes'] : 5;
    $defense_data->number_of_defenses = isset($post_data['number_of_defenses']) ? (int)$post_data['number_of_defenses'] : 4;
    $defense_data->insert_breaks = isset($post_data['insert_breaks']) ? (int)$post_data['insert_breaks'] : 0;
    $defense_data->how_many_breaks = isset($post_data['how_many_breaks']) ? (int)$post_data['how_many_breaks'] : 1;
    $defense_data->break_minutes = isset($post_data['break_minutes']) ? (int)$post_data['break_minutes'] : 10;
    $defense_data->note = isset($post_data['note']) ? trim($post_data['note']) : '';
    $defense_data->time_created = time();
    
    $DB->insert_record('block_poll_defense_settings', $defense_data);
}

/**
 * Handle custom timeslot options
 */
function handle_custom_timeslot_options($DB, $poll_id, $post_data) {
    if (isset($post_data['defense_minutes']) || isset($post_data['buffer_minutes']) || isset($post_data['number_of_defenses'])) {
        $defense_settings = new stdClass();
        $defense_settings->poll_id = $poll_id;
        $defense_settings->defense_minutes = isset($post_data['defense_minutes']) ? (int)$post_data['defense_minutes'] : 20;
        $defense_settings->buffer_minutes = isset($post_data['buffer_minutes']) ? (int)$post_data['buffer_minutes'] : 5;
        $defense_settings->number_of_defenses = isset($post_data['number_of_defenses']) ? (int)$post_data['number_of_defenses'] : 4;
        $defense_settings->insert_breaks = isset($post_data['insert_breaks']) ? (int)$post_data['insert_breaks'] : 0;
        $defense_settings->how_many_breaks = isset($post_data['how_many_breaks']) ? (int)$post_data['how_many_breaks'] : 1;
        $defense_settings->break_minutes = isset($post_data['break_minutes']) ? (int)$post_data['break_minutes'] : 10;
        $defense_settings->note = isset($post_data['note']) ? $post_data['note'] : '';
        $defense_settings->time_created = time();
        
        $DB->insert_record('block_poll_defense_settings', $defense_settings);
        error_log('Defense settings stored for poll ' . $poll_id . ': ' . json_encode($defense_settings));
    }
    
    if (isset($post_data['timeslots']) && is_array($post_data['timeslots'])) {
        foreach ($post_data['timeslots'] as $index => $timeslot) {
            if (!empty($timeslot['start_time']) && !empty($timeslot['end_time'])) {
                $option = new stdClass();
                $option->poll_id = $poll_id;
                $start_time = date('M j g:i A', (int)$timeslot['start_time']);
                $end_time = date('M j g:i A', (int)$timeslot['end_time']);
                $option->option_text = $start_time . ' - ' . $end_time;
                $option->start_time = (int)$timeslot['start_time'];
                $option->end_time = (int)$timeslot['end_time'];
                $option->sort_order = $index;
                $DB->insert_record('block_poll_options', $option);
            }
        }
    }
}

/**
 * Handle get poll results request
 */
function handle_get_poll_results() {
    global $DB, $USER;
    
    try {
        $poll_id = $_POST['poll_id'] ?? null;
        if (!$poll_id) {
            throw new Exception('Poll ID is required');
        }
        
        $poll = $DB->get_record('block_poll_polls', array('id' => $poll_id));
        if (!$poll) {
            throw new Exception('Poll not found with ID: ' . $poll_id);
        }
        
        // For export functionality, be more permissive - allow any authenticated user to export
        $context = context_system::instance();
        $has_admin_permission = has_capability('moodle/site:config', $context) || $USER->username === 'admin' || $USER->id == 1 || $USER->id == 2;
        $is_creator = ((int)$poll->created_by === (int)$USER->id);
        
        // Allow export for any authenticated user (more permissive for export functionality)
        $is_authenticated_user = $USER->id > 0 && !empty($USER->username) && !$USER->deleted && !$USER->suspended;
        
        if (!$has_admin_permission && !$is_creator && !$is_authenticated_user) {
            error_log("Permission denied for poll results - User: {$USER->id} ({$USER->username}), Admin: " . ($has_admin_permission ? 'YES' : 'NO') . 
                     ", Creator: " . ($is_creator ? 'YES' : 'NO') . 
                     ", Authenticated: " . ($is_authenticated_user ? 'YES' : 'NO'));
            throw new Exception('Insufficient permissions to view poll results - please log in with a valid account');
        }
        
        error_log("Poll results access granted - Poll: {$poll_id}, User: {$USER->id} ({$USER->username}), Admin: " . ($has_admin_permission ? 'YES' : 'NO') . ", Creator: " . ($is_creator ? 'YES' : 'NO') . ", Authenticated: " . ($is_authenticated_user ? 'YES' : 'NO'));
        error_log("User details - ID: {$USER->id}, Username: {$USER->username}, Deleted: " . ($USER->deleted ? 'YES' : 'NO') . ", Suspended: " . ($USER->suspended ? 'YES' : 'NO'));
        
        // Get poll options
        try {
            $options = $DB->get_records('block_poll_options', array('poll_id' => $poll_id), 'sort_order ASC');
            error_log('Poll options found: ' . count($options));
        } catch (Exception $e) {
            error_log('Error fetching poll options: ' . $e->getMessage());
            throw new Exception('Failed to fetch poll options: ' . $e->getMessage());
        }
        
        
        $professor_sql = "SELECT DISTINCT u.id, u.username, u.firstname, u.lastname, u.email
                          FROM {user} u
                          INNER JOIN {role_assignments} ra ON u.id = ra.userid
                          INNER JOIN {role} r ON ra.roleid = r.id
                          WHERE u.deleted = 0 AND u.suspended = 0
                          AND (r.shortname IN ('editingteacher', 'teacher', 'teacherrole', 'faculty', 'professor')
                               OR r.name LIKE '%teacher%'
                               OR r.name LIKE '%professor%'
                               OR r.name LIKE '%faculty%')
                          AND u.username NOT IN ('admin', 'moodle', 'developer')
                          ORDER BY u.firstname, u.lastname";
        
        try {
            $professors = $DB->get_records_sql($professor_sql);
            $total_professors = count($professors);
            error_log('Professors found: ' . $total_professors);
        } catch (Exception $e) {
            error_log('Error fetching professors: ' . $e->getMessage());
            // Fallback to basic professor query
            $professors = $DB->get_records('user', array('deleted' => 0, 'suspended' => 0), 'firstname, lastname');
            $total_professors = count($professors);
            error_log('Fallback professors found: ' . $total_professors);
        }
        
        $option_votes = [];
        $total_votes = 0;
        
        foreach ($options as $option) {
            $votes = $DB->get_records('block_poll_votes', array('poll_id' => $poll_id, 'option_id' => $option->id));
            $vote_count = count($votes);
            $total_votes += $vote_count;
            
            $option_votes[$option->id] = [
                'option' => $option,
                'votes' => $votes,
                'vote_count' => $vote_count,
                'percentage' => $total_professors > 0 ? round(($vote_count / $total_professors) * 100) : 0
            ];
        }
        
        $professor_voting_status = [];
        foreach ($professors as $professor) {
            $professor_voting_status[$professor->id] = [];
            foreach ($options as $option) {
                $has_voted = $DB->record_exists('block_poll_votes', array(
                    'poll_id' => $poll_id,
                    'option_id' => $option->id,
                    'user_id' => $professor->id
                ));
                $professor_voting_status[$professor->id][$option->id] = $has_voted;
            }
        }
        
        try {
            $all_votes = $DB->get_records('block_poll_votes', array('poll_id' => $poll_id));
        } catch (Exception $e) {
            error_log('Error fetching poll votes: ' . $e->getMessage());
            $all_votes = array();
        }
        
        $response = [
            'success' => true,
            'poll' => [
                'id' => $poll->id,
                'title' => $poll->title,
                'description' => $poll->description,
                'poll_type' => $poll->poll_type,
                'poll_mode' => $poll->poll_mode,
                'created_at' => $poll->time_created ?? time()
            ],
            'options' => array_values($options),
            'votes' => array_values($all_votes),
            'option_votes' => $option_votes,
            'professors' => array_values($professors),
            'professor_voting_status' => $professor_voting_status,
            'total_professors' => $total_professors,
            'total_votes' => $total_votes
        ];
        
        header('Content-Type: application/json');
        echo json_encode($response);
        
    } catch (Exception $e) {
        error_log('Poll results error: ' . $e->getMessage());
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Test export functionality
 */


/**
 * Handle vote submission
 */
function handle_submit_vote() {
    global $DB, $USER;
    
    try {
        error_log("Submit vote attempt - POST data: " . print_r($_POST, true));
        error_log("Current user: " . $USER->username . " (ID: " . $USER->id . ")");
        
        if (empty($_POST["poll_id"]) || empty($_POST["option_id"])) {
            throw new Exception("Poll ID and Option ID are required");
        }
        
        $poll_id = (int)$_POST["poll_id"];
        $option_id = (int)$_POST["option_id"];
        
        $poll = $DB->get_record("block_poll_polls", array("id" => $poll_id, "active" => 1));
        if (!$poll) {
            throw new Exception("Poll not found or inactive");
        }
        
        $option = $DB->get_record("block_poll_options", array("id" => $option_id, "poll_id" => $poll_id));
        if (!$option) {
            throw new Exception("Invalid option for this poll");
        }
        
        $existing_vote = $DB->get_record("block_poll_votes", array("poll_id" => $poll_id, "user_id" => $USER->id));
        error_log("Checking for existing vote - Poll: " . $poll_id . ", User: " . $USER->id . ", Existing vote: " . ($existing_vote ? "YES (ID: " . $existing_vote->id . ")" : "NO"));
        
        if ($existing_vote) {
            error_log("User " . $USER->id . " attempted to vote again in poll " . $poll_id . " - existing vote found: " . $existing_vote->id);
            throw new Exception("You have already submitted a PERMANENT vote for this poll. Votes cannot be changed or undone once submitted. Your existing vote was recorded on " . date('M j, g:i A', $existing_vote->time_voted));
        }
        
        // Check for professor roles in multiple contexts
        $has_professor_role = false;
        $all_user_roles = [];
        
        // Check system context
        $system_context = context_system::instance();
        $system_roles = get_user_roles($system_context, $USER->id);
        $all_user_roles = array_merge($all_user_roles, $system_roles);
        
        // Check course contexts where user might be a teacher
        $courses = enrol_get_users_courses($USER->id, true);
        foreach ($courses as $course) {
            $course_context = context_course::instance($course->id);
            $course_roles = get_user_roles($course_context, $USER->id);
            $all_user_roles = array_merge($all_user_roles, $course_roles);
        }
        
        error_log("All user roles for user " . $USER->id . ": " . print_r($all_user_roles, true));
        
        foreach ($all_user_roles as $role) {
            $role_name = strtolower($role->shortname);
            $role_display_name = strtolower($role->name);
            error_log("Checking role: " . $role->shortname . " (" . $role->name . ")");
            
            // Check for various professor/teacher role names
            if (in_array($role_name, ["editingteacher", "teacher", "teacherrole", "faculty", "professor", "manager", "admin"]) ||
                strpos($role_display_name, "teacher") !== false ||
                strpos($role_display_name, "professor") !== false ||
                strpos($role_display_name, "faculty") !== false ||
                strpos($role_display_name, "instructor") !== false ||
                strpos($role_display_name, "lecturer") !== false) {
                $has_professor_role = true;
                error_log("User has professor role: " . $role->shortname . " (" . $role->name . ")");
                break;
            }
        }
        
        // Also check if user has the voting capability directly
        if (!$has_professor_role) {
            if (has_capability('block/poll:vote', $system_context)) {
                $has_professor_role = true;
                error_log("User has block/poll:vote capability");
            }
        }
        
        if (!$has_professor_role) {
            $role_names = array_map(function($r) { return $r->shortname . " (" . $r->name . ")"; }, $all_user_roles);
            throw new Exception("Only professors can vote in polls. User roles: " . implode(", ", $role_names));
        }
        
        $vote = new stdClass();
        $vote->poll_id = $poll_id;
        $vote->option_id = $option_id;
        $vote->user_id = $USER->id;
        $vote->time_voted = time();
        
        $insertedid = $DB->insert_record("block_poll_votes", $vote);
        if ($insertedid) {
            error_log("Vote submitted successfully - Poll: " . $poll_id . ", Option: " . $option_id . ", User: " . $USER->id . ", Vote ID: " . $insertedid);

            $response = [
                "success" => true,
                "message" => "Vote submitted successfully",
                "vote_id" => $insertedid
            ];
        } else {
            throw new Exception("Failed to submit vote");
        }
        
        header("Content-Type: application/json");
        echo json_encode($response);
        
    } catch (Exception $e) {
        error_log("Vote submission error: " . $e->getMessage());
        header("Content-Type: application/json");
        http_response_code(400);
        echo json_encode(["success" => false, "message" => $e->getMessage()]);
    }
}

/**
 * Handle multiple choice vote submission (single submission, multiple options)
 */
function handle_submit_multiple_choice_vote() {
    global $DB, $USER;

    try {
        error_log("=== MULTIPLE CHOICE VOTE FUNCTION CALLED ===");
        error_log("POST data: " . print_r($_POST, true));
        error_log("User: " . $USER->username . " (ID: " . $USER->id . ")");
        error_log("Database connection status: " . ($DB ? "Connected" : "NOT CONNECTED"));
        error_log("Current timestamp: " . time());
        
        if (empty($_POST['poll_id']) || empty($_POST['option_ids']) || !is_array($_POST['option_ids'])) {
            error_log("Validation failed - poll_id: " . ($_POST['poll_id'] ?? 'empty') . ", option_ids: " . (isset($_POST['option_ids']) ? print_r($_POST['option_ids'], true) : 'NOT SET'));
            throw new Exception('Poll ID and option_ids[] are required');
        }

        $poll_id = (int)$_POST['poll_id'];
        $option_ids = array_map('intval', $_POST['option_ids']);

        error_log("=== POLL VALIDATION DEBUG ===");
        error_log("Looking for poll ID: " . $poll_id);
        
        $poll = $DB->get_record('block_poll_polls', ['id' => $poll_id, 'active' => 1], '*', IGNORE_MISSING);
        error_log("Poll lookup result: " . print_r($poll, true));
        
        if (!$poll) {
            error_log(" Poll not found or inactive");
            throw new Exception('Poll not found or inactive');
        }
        
        error_log(" Poll found: ID=" . $poll->id . ", Title=" . $poll->title . ", Type=" . $poll->poll_type . ", Active=" . $poll->active);
        
        if (($poll->poll_type ?? 'single') !== 'multiple') {
            error_log(" Poll is not multiple choice: " . ($poll->poll_type ?? 'single'));
            throw new Exception('This poll is not configured for multiple choice voting');
        }
        
        error_log(" Poll is multiple choice");

        $existingcount = $DB->count_records('block_poll_votes', ['poll_id' => $poll_id, 'user_id' => $USER->id]);
        if ($existingcount > 0) {
            throw new Exception('You have already submitted a PERMANENT vote for this poll. Votes cannot be changed or undone.');
        }

        error_log("=== OPTION VALIDATION DEBUG ===");
        error_log("Option IDs to validate: " . print_r($option_ids, true));
        error_log("Poll ID: " . $poll_id);
        
        $valid_options = [];
        foreach ($option_ids as $oid) {
            $option = $DB->get_record('block_poll_options', ['id' => $oid, 'poll_id' => $poll_id]);
            if ($option) {
                $valid_options[$oid] = $option;
                error_log(" Option " . $oid . " is valid for poll " . $poll_id);
            } else {
                error_log(" Option " . $oid . " NOT found for poll " . $poll_id);
                throw new Exception('Invalid option selected for this poll: ' . $oid . ' not found');
            }
        }
        
        error_log("All options validated successfully. Valid options: " . print_r(array_keys($valid_options), true));

        // Check for professor roles in multiple contexts
        $has_professor_role = false;
        $all_user_roles = [];
        
        // Check system context
        $system_context = context_system::instance();
        $system_roles = get_user_roles($system_context, $USER->id);
        $all_user_roles = array_merge($all_user_roles, $system_roles);
        
        // Check course contexts where user might be a teacher
        $courses = enrol_get_users_courses($USER->id, true);
        foreach ($courses as $course) {
            $course_context = context_course::instance($course->id);
            $course_roles = get_user_roles($course_context, $USER->id);
            $all_user_roles = array_merge($all_user_roles, $course_roles);
        }
        
        error_log("All user roles for user " . $USER->id . ": " . print_r($all_user_roles, true));
        
        foreach ($all_user_roles as $role) {
            $role_name = strtolower($role->shortname);
            $role_display_name = strtolower($role->name);
            error_log("Checking role: " . $role->shortname . " (" . $role->name . ")");
            
            // Check for various professor/teacher role names
            if (in_array($role_name, ["editingteacher", "teacher", "teacherrole", "faculty", "professor", "manager", "admin"]) ||
                strpos($role_display_name, "teacher") !== false ||
                strpos($role_display_name, "professor") !== false ||
                strpos($role_display_name, "faculty") !== false ||
                strpos($role_display_name, "instructor") !== false ||
                strpos($role_display_name, "lecturer") !== false) {
                $has_professor_role = true;
                error_log("User has professor role: " . $role->shortname . " (" . $role->name . ")");
                break;
            }
        }
        
        // Also check if user has the voting capability directly
        if (!$has_professor_role) {
            if (has_capability('block/poll:vote', $system_context)) {
                $has_professor_role = true;
                error_log("User has block/poll:vote capability");
            }
        }
        
        if (!$has_professor_role) {
            $role_names = array_map(function($r) { return $r->shortname . " (" . $r->name . ")"; }, $all_user_roles);
            throw new Exception("Only professors can vote in polls. User roles: " . implode(", ", $role_names));
        }

        $tx = $DB->start_delegated_transaction();
        foreach ($option_ids as $oid) {
            $vote = (object)[
                'poll_id' => $poll_id,
                'option_id' => $oid,
                'user_id' => $USER->id,
                'time_voted' => time(),
            ];
            $DB->insert_record('block_poll_votes', $vote);
        }
        $tx->allow_commit();

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Vote submitted successfully']);

    } catch (Exception $e) {
        error_log('Multiple choice vote error: ' . $e->getMessage());
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**/ 
