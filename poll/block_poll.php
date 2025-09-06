<?php
defined('MOODLE_INTERNAL') || die();

class block_poll extends block_base {

    public function init() {
        $this->title = get_string('pluginname', 'block_poll');
    }

    /**
     * Format poll mode text for display
     */
    private function formatPollMode($mode) {
        switch ($mode) {
            case 'custom_timeslot':
                return 'Custom Timeslot';
            case 'time':
                return 'Time Slots';
            case 'timeslot': // legacy alias
                return 'Time Slots';
            case 'text':
                return 'Text Options';
            default:
                return ucwords(str_replace('_', ' ', $mode));
        }
    }

    public function get_content() {
        global $USER, $CFG, $DB, $OUTPUT, $PAGE;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $context = context_system::instance();
        
        $is_manager = false;
        $is_teacher = false;
        
        if (has_capability('moodle/site:config', $context)) {
            $is_manager = true;
        }
        
        if (has_capability('moodle/course:create', $context) || has_capability('moodle/course:manageactivities', $context)) {
            $is_teacher = true;
        }
        
        // Also check for course-level teacher roles
        if (!$is_teacher) {
            $courses = enrol_get_users_courses($USER->id, true);
            foreach ($courses as $course) {
                $course_context = context_course::instance($course->id);
                if (has_capability('moodle/course:manageactivities', $course_context) || 
                    has_capability('moodle/course:create', $course_context)) {
                    $is_teacher = true;
                    break;
                }
            }
        }
        
        if ($USER->username === 'admin' || $USER->id == 1) {
            $is_manager = true;
        }
        
        if ($is_manager) {
            $canmanage = true;
            $canvote = true;
            $role_display = 'Manager/Admin';
        } elseif ($is_teacher) {
            $canmanage = false;
            $canvote = true;
            $role_display = 'Teacher/Professor';
        } else {
            $canmanage = false;
            $canvote = false;
            $role_display = 'User';
        }
        


        $PAGE->requires->css(new moodle_url('/blocks/poll/styles/Manager.css'));
        $PAGE->requires->css(new moodle_url('/blocks/poll/styles/professor.css'));
        
        
        $content = '<div id="pollModal" class="modal-overlay">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title" id="modalTitle">Notification</h3>
                </div>
                <div class="modal-body">
                    <span class="modal-icon" id="modalIcon"></span>
                    <p class="modal-message" id="modalMessage">Message content</p>
                </div>
                <div class="modal-footer">
                    <button class="modal-btn" id="modalBtn" onclick="closeModal()">OK</button>
                    <button class="modal-btn modal-btn-secondary" id="modalCancelBtn" onclick="closeModal()" style="display: none;">Cancel</button>
                </div>
            </div>
        </div>';
        
        // Language toggle system
        $current_lang = isset($_COOKIE['poll_language']) ? $_COOKIE['poll_language'] : 'en';
        $other_lang = $current_lang === 'en' ? 'ru' : 'en';
        $current_lang_name = $current_lang === 'en' ? 'English' : 'Русский';
        $other_lang_name = $other_lang === 'en' ? 'English' : 'Русский';
        
        $content .= '<div class="block_poll_content" style="font-size: 12px;">';
        
        // Language toggle button
        $content .= '<div class="language-toggle" style="text-align: right; margin-bottom: 15px;">
            <button id="langToggle" class="lang-btn" data-current="' . $current_lang . '" data-other="' . $other_lang . '" style="background: #007bff; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-size: 12px; font-weight: 500;">
                ' . $other_lang_name . ' / ' . $current_lang_name . '
            </button>
        </div>';
        
        
        $content .= '<script src="/blocks/poll/amd/src/professor.js?v=' . time() . '"></script>';
        
        // Language system JavaScript
        $content .= '<script>
        // Language system
        const languageStrings = {
            en: {
                "Create Poll": "Create Poll",
                "Poll Results": "Poll Results",
                "Export All": "Export All",
                "Export Selected": "Export Selected",
                "Delete Selected": "Delete Selected",
                "Create poll": "Create poll",
                "One vote per user": "One vote per user",
                "Multiple votes per user allowed": "Multiple votes per user allowed",
                "Loading...": "Loading...",
                "Fetching poll results...": "Fetching poll results...",
                "Error": "Error",
                "Success": "Success",
                "Warning": "Warning",
                "Info": "Info",
                "Delete": "Delete",
                "Cancel": "Cancel",
                "Confirm": "Confirm",
                "Close": "Close",
                "Export": "Export",
                "No polls available to export": "No polls available to export",
                "Please select polls to export": "Please select polls to export",
                "Please select polls to delete": "Please select polls to delete",
                "Are you sure you want to delete": "Are you sure you want to delete",
                "Polls to be deleted:": "Polls to be deleted:",
                "Poll to be deleted:": "Poll to be deleted:",
                "Successfully deleted": "Successfully deleted",
                "poll(s)": "poll(s)",
                "Deleted": "Deleted",
                "Failed": "Failed",
                "Progress": "Progress",
                "of": "of",
                "Export Summary": "Export Summary",
                "Export Date": "Export Date",
                "Successfully Exported": "Successfully Exported",
                "Failed to Export": "Failed to Export",
                "Exported Polls": "Exported Polls",
                "Exported": "Exported",
                "Network error occurred": "Network error occurred",
                "Unknown error": "Unknown error",
                "Partial Success": "Partial Success",
                "Check console for details": "Check console for details",
                
                // Form elements and labels
                "Title": "Title",
                "Type your question here": "Type your question here",
                "Description": "Description",
                "Add poll description (optional)": "Add poll description (optional)",
                "Poll type": "Poll type",
                "Single choice": "Single choice",
                "Multiple choice": "Multiple choice",
                "Poll mode": "Poll mode",
                "Text options": "Text options",
                "Time slots": "Time slots",
                "Custom Timeslot": "Custom Timeslot",
                "Answer Options": "Answer Options",
                "Option": "Option",
                "Add option": "Add option",
                "Voting security": "Voting security",
                "One vote per user": "One vote per user",
                "Multiple votes per user allowed": "Multiple votes per user allowed",
                
                // Non-voting professors section
                "Non-Voting Professors": "Non-Voting Professors",
                "Collapse": "Collapse",
                "Course Schedule Preferences": "Course Schedule Preferences",
                "Student Housing Feedback": "Student Housing Feedback",
                "Academic Calendar Preferences": "Academic Calendar Preferences",
                "voted": "voted",
                "not voted": "not voted",
                "participation rate": "participation rate",
                "Click to see details": "Click to see details",
                "Overall Voting Statistics": "Overall Voting Statistics",
                "total votes": "total votes",
                "total not voted": "total not voted",
                "polls": "polls",
                
                // Additional UI elements
                "Search polls...": "Search polls...",
                "Select All": "Select All",
                "Deselect All": "Deselect All",
                "Loading polls...": "Loading polls...",
                "Loading poll statistics...": "Loading poll statistics...",
                "Loading statistics...": "Loading statistics...",
                "Loading counts...": "Loading counts...",
                
                // Professor details and voting status
                "Professor Details": "Professor Details",
                "Voting Summary": "Voting Summary",
                "participation": "participation",
                "Professors who did not vote": "Professors who did not vote",
                "Please select your preferred time slots for": "Please select your preferred time slots for",
                "Multiple Choice": "Multiple Choice",
                "Single Choice": "Single Choice",
                "Text": "Text",
                "Custom Time Slot": "Custom Time Slot",
                "voters": "voters",
                "Export": "Export",
                "View Results": "View Results",
                "Delete Poll": "Delete Poll",
                
                // Additional UI elements that need translation
                "voted": "voted",
                "not voted": "not voted",
                "participation rate": "participation rate",
                "Click to see details": "Click to see details",
                "polls": "polls",
                "total votes": "total votes",
                "total not voted": "total not voted",
                "Voted": "Voted",
                "Not Voted": "Not Voted",
                
                // Modal and detail translations
                "Professor Details": "Professor Details",
                "Voting Summary:": "Voting Summary:",
                "Professors who did not vote": "Professors who did not vote",
                "Close": "Close",
                "voters": "voters",
                
                // Additional modal translations
                "Professors who voted": "Professors who voted",
                "All professors voted!": "All professors voted!",
                
                // Time-slot and defense settings translations
                "Time-slot Settings": "Time-slot Settings",
                "Time-slot settings": "Time-slot settings",
                "Configure time-based voting options. Add multiple time slots for users to choose from.": "Configure time-based voting options. Add multiple time slots for users to choose from.",
                "Start date & time": "Start date & time",
                "End date & time": "End date & time",
                "Add time slot": "Add time slot",
                "Set end time manually": "Set end time manually",
                "Defense Settings": "Defense Settings",
                "Defense minutes": "Defense minutes",
                "Buffer minutes": "Buffer minutes",
                "Number of defenses": "Number of defenses",
                "Insert breaks": "Insert breaks",

                "Preview": "Preview",
                "Set parameters to see preview.": "Set parameters to see preview.",
                
                // Additional break-related translations
                "How many breaks": "How many breaks",
                "Break minutes": "Break minutes",
                
                // Additional UI elements from image and user request
                "Available Polls": "Available Polls",
                "Description": "Description",
                "Text Poll": "Text Poll",
                "options": "options",
                "Created": "Created",
                "Select your vote (you can choose multiple options):": "Select your vote (you can choose multiple options):",
                
                // Poll results view translations
                "Professor": "Professor",
                "participants": "participants",
                "options": "options",
                "Search professor...": "Search professor...",
                
                // Additional poll metadata translations
                "VOTED": "VOTED",
                "PERMANENT VOTE": "PERMANENT VOTE",
                "OPTIONS": "OPTIONS",
                "Selected Options": "Selected Options",
                "Your vote": "Your vote",
                "Your votes": "Your votes",
                "Submit Your Vote": "Submit Your Vote",
                "submit_your_vote": "Submit Your Vote",
                
                // Additional voting status translations
                "voted_label": "VOTED",
                "permanent_vote": "PERMANENT VOTE",
                "your_vote": "Your vote",
                "your_votes": "Your votes",
                "vote_cannot_be_changed": "This vote cannot be changed or undone.",
                "options_selected": "OPTIONS",
                "selected_options": "Selected Options",
                "voted": "✓ VOTED",
                "not_voted": "⚠ NOT VOTED",
                
                // Poll type translations
                "text_poll_type": "Text Poll",
                "time_slots": "Time Slots",
                "custom_defense_slots": "Custom Defense Slots",
                
                // Additional missing translations
                "options_count_label": "options",
                "created_on_label": "Created",
                "select_your_vote_multiple": "Select your vote (you can choose multiple options):",
                "select_your_vote_single": "Select your vote:",
                
                // Poll choice type translations (lowercase keys for data-translate)
                "multiple_choice": "Multiple Choice",
                "single_choice": "Single Choice"
            },
            ru: {
                "Create Poll": "Создать опрос",
                "Poll Results": "Результаты опроса",
                "Export All": "Экспортировать все",
                "Export Selected": "Экспортировать выбранные",
                "Delete Selected": "Удалить выбранные",
                "Create poll": "Создать опрос",
                "One vote per user": "Один голос на пользователя",
                "Multiple votes per user allowed": "Разрешены множественные голоса",
                "Loading...": "Загрузка...",
                "Fetching poll results...": "Получение результатов опроса...",
                "Error": "Ошибка",
                "Success": "Успешно",
                "Warning": "Предупреждение",
                "Info": "Информация",
                "Delete": "Удалить",
                "Cancel": "Отмена",
                "Confirm": "Подтвердить",
                "Close": "Закрыть",
                "Export": "Экспорт",
                "No polls available to export": "Нет доступных опросов для экспорта",
                "Please select polls to export": "Пожалуйста, выберите опросы для экспорта",
                "Please select polls to delete": "Пожалуйста, выберите опросы для удаления",
                "Are you sure you want to delete": "Вы уверены, что хотите удалить",
                "Polls to be deleted:": "Опросы для удаления:",
                "Poll to be deleted:": "Опрос для удаления:",
                "Successfully deleted": "Успешно удалено",
                "poll(s)": "опрос(ов)",
                "Deleted": "Удалено",
                "Failed": "Не удалось",
                "Progress": "Прогресс",
                "of": "из",
                "Export Summary": "Сводка экспорта",
                "Export Date": "Дата экспорта",
                "Successfully Exported": "Успешно экспортировано",
                "Failed to Export": "Не удалось экспортировать",
                "Exported Polls": "Экспортированные опросы",
                "Exported": "Экспортировано",
                "Network error occurred": "Произошла сетевая ошибка",
                "Unknown error": "Неизвестная ошибка",
                "Partial Success": "Частичный успех",
                "Check console for details": "Проверьте консоль для деталей",
                
                // Form elements and labels
                "Title": "Заголовок",
                "Type your question here": "Введите ваш вопрос здесь",
                "Description": "Описание",
                "Add poll description (optional)": "Добавить описание опроса (необязательно)",
                "Poll type": "Тип опроса",
                "Single choice": "Один выбор",
                "Multiple choice": "Множественный выбор",
                "Poll mode": "Режим опроса",
                "Text options": "Текстовые варианты",
                "Time slots": "Временные слоты",
                "Custom Timeslot": "Пользовательский временной слот",
                "Answer Options": "Варианты ответов",
                "Option": "Вариант",
                "Add option": "Добавить вариант",
                "Voting security": "Безопасность голосования",
                "One vote per user": "Один голос на пользователя",
                "Multiple votes per user allowed": "Разрешены множественные голоса",
                
                // Non-voting professors section
                "Non-Voting Professors": "Профессора, не проголосовавшие",
                "Collapse": "Свернуть",
                "Course Schedule Preferences": "Предпочтения расписания курсов",
                "Student Housing Feedback": "Отзывы о студенческом жилье",
                "Academic Calendar Preferences": "Предпочтения академического календаря",
                "voted": "проголосовали",
                "not voted": "не проголосовали",
                "participation rate": "процент участия",
                "Click to see details": "Нажмите для просмотра деталей",
                "Overall Voting Statistics": "Общая статистика голосования",
                "total votes": "всего голосов",
                "total not voted": "всего не проголосовали",
                "polls": "опросов",
                
                // Additional UI elements
                "Search polls...": "Поиск опросов...",
                "Select All": "Выбрать все",
                "Deselect All": "Снять выбор",
                "Loading polls...": "Загрузка опросов...",
                "Loading poll statistics...": "Загрузка статистики опросов...",
                "Loading statistics...": "Загрузка статистики...",
                "Loading counts...": "Загрузка подсчетов...",
                
                // Professor details and voting status
                "Professor Details": "Детали профессора",
                "Voting Summary": "Сводка голосования",
                "participation": "участие",
                "Professors who did not vote": "Профессора, которые не проголосовали",
                "Please select your preferred time slots for": "Пожалуйста, выберите предпочтительные временные слоты для",
                "Multiple Choice": "Множественный выбор",
                "Single Choice": "Один выбор",
                "Text": "Текст",
                "Custom Time Slot": "Пользовательский временной слот",
                "voters": "голосующих",
                "Export": "Экспорт",
                "View Results": "Просмотр результатов",
                "Delete Poll": "Удалить опрос",
                
                // Additional UI elements that need translation
                "voted": "проголосовали",
                "not voted": "не проголосовали",
                "participation rate": "процент участия",
                "Click to see details": "Нажмите для просмотра деталей",
                "polls": "опросов",
                "total votes": "всего голосов",
                "total not voted": "всего не проголосовали",
                "Voted": "Проголосовали",
                "Not Voted": "Не проголосовали",
                
                // Modal and detail translations
                "Professor Details": "Детали профессора",
                "Voting Summary:": "Сводка голосования:",
                "Professors who did not vote": "Профессора, которые не проголосовали",
                "Close": "Закрыть",
                "voters": "голосующих",
                
                // Additional modal translations
                "Professors who voted": "Профессора, которые проголосовали",
                "All professors voted!": "Все профессора проголосовали!",
                
                // Time-slot and defense settings translations
                "Time-slot Settings": "Настройки временных слотов",
                "Time-slot settings": "Настройки временных слотов",
                "Configure time-based voting options. Add multiple time slots for users to choose from.": "Настройте варианты голосования по времени. Добавьте несколько временных слотов для выбора пользователями.",
                "Start date & time": "Дата и время начала",
                "End date & time": "Дата и время окончания",
                "Add time slot": "Добавить временной слот",
                "Set end time manually": "Установить время окончания вручную",
                "Defense Settings": "Настройки защиты",
                "Defense minutes": "Минуты защиты",
                "Buffer minutes": "Буферные минуты",
                "Number of defenses": "Количество защит",
                "Insert breaks": "Вставить перерывы",

                "Preview": "Предварительный просмотр",
                "Set parameters to see preview.": "Установите параметры для предварительного просмотра.",
                
                // Additional break-related translations
                "How many breaks": "Сколько перерывов",
                "Break minutes": "Минуты перерыва",
                
                // Additional UI elements from image and user request
                "Available Polls": "Доступные опросы",
                "Description": "Описание",
                "Text Poll": "Текстовый опрос",
                "options": "вариантов",
                "Created": "Создан",
                "Select your vote (you can choose multiple options):": "Выберите ваш голос (можно выбрать несколько вариантов):",
                
                // Poll results view translations
                "Professor": "Профессор",
                "participants": "участников",
                "options": "вариантов",
                "Search professor...": "Поиск профессора...",
                
                // Additional poll metadata translations
                "VOTED": "ПРОГОЛОСОВАЛ",
                "PERMANENT VOTE": "ПОСТОЯННЫЙ ГОЛОС",
                "OPTIONS": "ВАРИАНТОВ",
                "Selected Options": "Выбранные варианты",
                "Your vote": "Ваш голос",
                "Your votes": "Ваши голоса",
                "Submit Your Vote": "Отправить ваш голос",
                "submit_your_vote": "Отправить ваш голос",
                
                // Additional voting status translations
                "voted_label": "ПРОГОЛОСОВАЛ",
                "permanent_vote": "ПОСТОЯННЫЙ ГОЛОС",
                "your_vote": "Ваш голос",
                "your_votes": "Ваши голоса",
                "vote_cannot_be_changed": "Этот голос нельзя изменить или отменить.",
                "options_selected": "ВАРИАНТОВ",
                "selected_options": "Выбранные варианты",
                "voted": "✓ ПРОГОЛОСОВАЛ",
                "not_voted": "⚠ НЕ ПРОГОЛОСОВАЛ",
                
                // Poll type translations
                "text_poll_type": "Текстовый опрос",
                "time_slots": "Временные слоты",
                "custom_defense_slots": "Пользовательские слоты защиты",
                
                // Additional missing translations
                "options_count_label": "вариантов",
                "created_on_label": "Создан",
                "select_your_vote_multiple": "Выберите ваш голос (можно выбрать несколько вариантов):",
                "select_your_vote_single": "Выберите ваш голос:",
                
                // Vote confirmation modal translations
                "PERMANENT VOTE CONFIRMATION": "ПОДТВЕРЖДЕНИЕ ПОСТОЯННОГО ГОЛОСА",
                "This vote cannot be changed once submitted!": "Этот голос нельзя изменить после отправки!",
                "You are about to vote for:": "Вы собираетесь проголосовать за:",
                "Continue?": "Продолжить?",
                "Submit Vote": "Отправить голос",
                "Cancel": "Отмена",
                
                // Poll choice type translations (lowercase keys for data-translate)
                "multiple_choice": "Множественный выбор",
                "single_choice": "Один выбор"
            }
        };
        
        // Get current language
        function getCurrentLanguage() {
            const cookie = document.cookie.split("; ").find(row => row.startsWith("poll_language="));
            return cookie ? cookie.split("=")[1] : "en";
        }
        
        // Get translated text for a given key
        function getTranslatedText(key) {
            const currentLang = getCurrentLanguage();
            const strings = languageStrings[currentLang];
            return strings[key] || key;
        }
        
        // Set language cookie
        function setLanguage(lang) {
            document.cookie = "poll_language=" + lang + "; path=/; max-age=31536000"; // 1 year
        }
        
        // Translate text
        function translateText(text) {
            const currentLang = getCurrentLanguage();
            return languageStrings[currentLang][text] || text;
        }
        
        // Translate all text on the page
        function translatePage() {
            const currentLang = getCurrentLanguage();
            const strings = languageStrings[currentLang];
            
            // Translate specific elements
            const elements = document.querySelectorAll("[data-translate]");
            elements.forEach(el => {
                const key = el.getAttribute("data-translate");
                if (strings[key]) {
                    el.textContent = strings[key];
                }
            });
            
            // Translate placeholders
            const placeholderElements = document.querySelectorAll("[data-translate-placeholder]");
            placeholderElements.forEach(el => {
                const key = el.getAttribute("data-translate-placeholder");
                if (strings[key]) {
                    el.placeholder = strings[key];
                }
            });
            
            // Translate hardcoded text BUT EXCLUDE POLL TITLES
            const textNodes = document.querySelectorAll("h3, button, span, label, div, p, option");
            textNodes.forEach(node => {
                if (node.childNodes.length === 1 && node.childNodes[0].nodeType === 3) {
                    const text = node.textContent.trim();
                    

                    
                    if (strings[text]) {
                        node.textContent = strings[text];
                    }
                }
            });
            
            // Update language toggle button
            updateLanguageToggle();
        }
        

        
        // Update language toggle button
        function updateLanguageToggle() {
            const currentLang = getCurrentLanguage();
            const otherLang = currentLang === "en" ? "ru" : "en";
            const currentLangName = currentLang === "en" ? "English" : "Русский";
            const otherLangName = otherLang === "en" ? "English" : "Русский";
            
            const toggleBtn = document.getElementById("langToggle");
            if (toggleBtn) {
                toggleBtn.textContent = otherLangName + " / " + currentLangName;
                toggleBtn.setAttribute("data-current", currentLang);
                toggleBtn.setAttribute("data-other", otherLang);
            }
        }
        
        // Toggle language
        function toggleLanguage() {
            const currentLang = getCurrentLanguage();
            const newLang = currentLang === "en" ? "ru" : "en";
            
            setLanguage(newLang);
            translatePage();
            
            // Show language change notification
            if (typeof showModal === "function") {
                const langName = newLang === "en" ? "English" : "Русский";
                showModal("Language Changed", "Language changed to " + langName, "info");
            }
        }
        
        // Initialize language system
        document.addEventListener("DOMContentLoaded", function() {
            const langToggle = document.getElementById("langToggle");
            if (langToggle) {
                langToggle.addEventListener("click", toggleLanguage);
            }
            
            // Initial translation
            translatePage();
        });
        </script>';
        
        if (!empty($debug_info)) {
            $content .= $debug_info;
        }

        if ($canmanage) {
            $content .= $this->get_manager_content();
        } elseif ($canvote) {
            $content .= $this->get_professor_content();
        } else {
            $content .= '<p style="color: #666; text-align: center; padding: 10px;">' . get_string('no_access', 'block_poll') . '</p>';
        }

            $content .= '</div>';

        $this->content->text = $content;
        $this->content->footer = '';

            return $this->content;
        }

    private function get_manager_content() {
        global $DB, $USER;
        
        $content = '';
        
        $polls = $DB->get_records('block_poll_polls', array('active' => 1), 'time_created DESC', '*', 0, 3);
        
        $content .= '<div class="poll-moodle-theme">
            <div class="card">
                <div class="poll-head">
                    <h3 data-translate="Create Poll">Create Poll</h3>
                </div>
                                <div>
                    <label class="req" data-translate="Title">Title</label>
                    <input id="pollTitle" class="inp" placeholder="Type your question here" data-translate-placeholder="Type your question here">
                    <label data-translate="Description">Description</label>
                    <textarea id="pollDesc" class="inp" rows="3" placeholder="Add poll description (optional)" data-translate-placeholder="Add poll description (optional)"></textarea>
                    <div class="form-grid">
                        <div>
                            <label class="req" data-translate="Poll type">Poll type</label>
                            <select id="pollType" class="sel" onchange="pollTypeChanged()">
                                <option value="single" data-translate="Single choice">Single choice</option>
                                <option value="multiple" data-translate="Multiple choice">Multiple choice</option>
                            </select>
                        </div>
                                                    <div>
                                <label class="req" data-translate="Poll mode">Poll mode</label>
                                <select id="pollMode" class="sel" onchange="pollModeChanged()">
                                    <option value="text" data-translate="Text options">Text options</option>
                                    <option value="time" data-translate="Time slots">Time slots</option>
                                    <option value="custom_timeslot" data-translate="Custom Timeslot">Custom Timeslot</option>
                                </select>
                            </div>
                    </div>
                    
                    <!-- Text Options Section -->
                    <div id="pollAnswerText">
                        <label class="req" data-translate="Answer Options">Answer Options</label>
                        <div id="pollOptionsBox">
                            <div class="poll-opt">
                                <input class="inp poll-opt-input" placeholder="Option 1" data-translate-placeholder="Option 1">
                                <button class="mini danger" onclick="removePollOption(this)">×</button>
                            </div>
                            <div class="poll-opt">
                                <input class="inp poll-opt-input" placeholder="Option 2" data-translate-placeholder="Option 2">
                                <button class="mini danger" onclick="removePollOption(this)">×</button>
                            </div>
                        </div>
                        <div class="inline">
                            <button class="mini" onclick="addPollOption()" data-translate="Add option">Add option</button>
                        </div>
                        <div class="muted" data-translate="Voting security">Voting security</div>
                        <div class="meta-row">
                            <span class="meta-chip" id="pollSecurityNote" data-translate="One vote per user">One vote per user</span>
                        </div>
                    </div>
                    
                    <!-- Time Slots Section -->
                    <div id="pollAnswerTime" style="display:none">
                        <div class="timeslot-option">
                            <h4 data-translate="Time-slot Settings">Time-slot Settings</h4>
                            <p data-translate="Configure time-based voting options. Add multiple time slots for users to choose from.">Configure time-based voting options. Add multiple time slots for users to choose from.</p>
                            <div id="timeSlotsContainer">
                                <div class="time-slot-option" data-timeslot="1">
                                    <div class="timeslot-grid">
                                        <div class="timeslot-input">
                                            <label class="req" data-translate="Start date & time">Start date & time</label>
                                            <input class="inp dtp-input time-start" type="datetime-local">
                                        </div>
                                        <div class="timeslot-input">
                                            <label class="req" data-translate="End date & time">End date & time</label>
                                            <input class="inp dtp-input time-end" type="datetime-local">
                                        </div>
                                    </div>
                                    <button class="remove-time-slot" onclick="removeTimeSlot(this)">×</button>
                                </div>
                                <div class="time-slot-option" data-timeslot="2">
                                    <div class="timeslot-grid">
                                        <div class="timeslot-input">
                                            <label class="req" data-translate="Start date & time">Start date & time</label>
                                            <input class="inp dtp-input time-start" type="datetime-local">
                                        </div>
                                        <div class="timeslot-input">
                                            <label class="req" data-translate="End date & time">End date & time</label>
                                            <input class="inp dtp-input time-end" type="datetime-local">
                                        </div>
                                    </div>
                                    <button class="remove-time-slot" onclick="removeTimeSlot(this)">×</button>
                                </div>
                            </div>
                            <div class="inline">
                                <button class="add-time-slot-btn" onclick="addTimeSlot()" data-translate="+ Add time slot">+ Add time slot</button>
                            </div>
                            <div class="checkbox-group">
                                <input id="ptManualEnd" type="checkbox">
                                <span class="checkbox-label" data-translate="Set end time manually">Set end time manually</span>
                            </div>
                        </div>
                    </div>
                    
                    <div id="pollAnswerCustomTimeslot" style="display:none">
                        <div class="timeslot-option">
                            <h4 data-translate="Time-slot settings">Time-slot settings</h4>
                            <!-- Start and End Time Fields -->
                            <div class="timeslot-grid">
                                <div class="timeslot-input">
                                    <label class="req" data-translate="Start date & time">Start date & time</label>
                                    <input class="inp custom-start" type="datetime-local" id="customStartTime" onchange="updateCustomEndTime()">
                                </div>
                                <div class="timeslot-input">
                                    <label class="req" data-translate="End date & time">End date & time</label>
                                    <input class="inp custom-end" type="datetime-local" id="customEndTime" readonly>
                                </div>
                            </div>
                            
                            <!-- Set End Time Manually Checkbox -->
                            <div class="checkbox-group">
                                <input type="checkbox" id="setEndTimeManually" onchange="toggleEndTimeManual()">
                                <label class="checkbox-label" for="setEndTimeManually" data-translate="Set end time manually">Set end time manually</label>
                            </div>
                            
                            <!-- Defense Settings -->
                            <div class="defense-settings">
                                <h5 data-translate="Defense Settings">Defense Settings</h5>
                                <div class="defense-grid">
                                                                    <div class="defense-input">
                                    <label class="req" data-translate="Defense minutes">Defense minutes</label>
                                    <input class="inp" type="number" id="defenseMinutesCustom" value="20" min="1" max="120">
                                </div>
                                <div class="break-input">
                                    <label class="req" data-translate="Buffer minutes">Buffer minutes</label>
                                    <input class="inp" type="number" id="bufferMinutesCustom" value="5" min="1" max="60">
                                </div>
                                <div class="defense-input">
                                    <label class="req" data-translate="Number of defenses">Number of defenses</label>
                                    <input class="inp" type="number" id="numberOfDefensesCustom" value="4" min="1" max="20">
                                </div>
                                </div>
                            </div>
                            
                            <!-- Insert Breaks -->
                            <div class="breaks-section">
                                <div class="checkbox-group">
                                    <input type="checkbox" id="insertBreaksCustom" onchange="toggleBreaksCustom()">
                                    <label class="checkbox-label" for="insertBreaksCustom" data-translate="Insert breaks">Insert breaks</label>
                                </div>
                                <div id="breaksOptionsCustom" style="display:none">
                                    <div class="breaks-grid">
                                        <div class="break-input">
                                            <label data-translate="How many breaks">How many breaks</label>
                                            <input class="inp" type="number" id="howManyBreaksCustom" value="1" min="1" max="10">
                                        </div>
                                        <div class="break-input">
                                            <label data-translate="Break minutes">Break minutes</label>
                                            <input class="inp" type="number" id="breakMinutesCustom" value="10" min="1" max="60">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Preview Section -->
                            <div class="preview-section">
                            <h5 data-translate="Preview">Preview</h5>
                                <div id="customTimeslotPreview" class="preview-content">
                                <span data-translate="Set parameters to see preview.">Set parameters to see preview.</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    

                    
                                    <div class="inline">
                                            <button class="btn primary" onclick="createPoll(event)" data-translate="Create poll">Create poll</button>
                </div>
                </div>
            </div>
        </div>
        </div>';

        $content .= '<script>
        function pollTypeChanged() {
            const pollType = document.getElementById("pollType").value;
            const securityNote = document.getElementById("pollSecurityNote");
            
            if (pollType === "multiple") {
                securityNote.textContent = getTranslatedText("Multiple votes per user allowed");
                securityNote.classList.add("multiple-choice");
            } else {
                securityNote.textContent = getTranslatedText("One vote per user");
                securityNote.classList.remove("multiple-choice");
            }
        }

        function pollModeChanged() {
            const pollMode = document.getElementById("pollMode").value;
            
            document.getElementById("pollAnswerText").style.display = "none";
            document.getElementById("pollAnswerTime").style.display = "none";
            document.getElementById("pollAnswerCustomTimeslot").style.display = "none";
            
            switch(pollMode) {
                case "text":
                    document.getElementById("pollAnswerText").style.display = "block";
                    break;
                case "time":
                    document.getElementById("pollAnswerTime").style.display = "block";
                    initializeTimeInputs();
                    break;
                case "custom_timeslot":
                    document.getElementById("pollAnswerCustomTimeslot").style.display = "block";
                    initializeCustomTimeslotInputs();
                    break;
            }
        }

        function addPollOption() {
            const optionsBox = document.getElementById("pollOptionsBox");
            const optionCount = optionsBox.children.length + 1;
            
            const newOption = document.createElement("div");
            newOption.className = "poll-opt";
            newOption.innerHTML = \'<input class="inp poll-opt-input" placeholder="\' + getTranslatedText("Option") + \' \' + optionCount + \'"><button class="mini danger" onclick="removePollOption(this)">×</button>\';
            
            optionsBox.appendChild(newOption);
        }

        function removePollOption(button) {
            const optionsBox = document.getElementById("pollOptionsBox");
            const options = optionsBox.children;
            
            if (options.length > 2) {
                button.parentElement.remove();
                renumberOptions();
            }
        }

        function renumberOptions() {
            const options = document.querySelectorAll("#pollOptionsBox .poll-opt");
            options.forEach(function(option, index) {
                option.querySelector("input").placeholder = getTranslatedText("Option") + " " + (index + 1);
            });
        }

        function addTimeSlot() {
            const container = document.getElementById("timeSlotsContainer");
            const timeslotCount = container.children.length + 1;
            
            const newTimeslot = document.createElement("div");
            newTimeslot.className = "time-slot-option";
            newTimeslot.setAttribute("data-timeslot", timeslotCount);
            newTimeslot.innerHTML = \'<div class="timeslot-grid"><div class="timeslot-input"><label class="req">Start date & time</label><input class="inp dtp-input time-start" type="datetime-local"></div><div class="timeslot-input"><label class="req">End date & time</label><input class="inp dtp-input time-end" type="datetime-local"></div></div><button class="remove-time-slot" onclick="removeTimeSlot(this)">×</button>\';
            
            container.appendChild(newTimeslot);
            renumberTimeSlots();
            
            const newStartInput = newTimeslot.querySelector(".time-start");
            const newEndInput = newTimeslot.querySelector(".time-end");
            
            if (newStartInput) {
                newStartInput.addEventListener("click", function() {
                    this.showPicker();
                });
            }
            
            if (newEndInput) {
                newEndInput.addEventListener("click", function() {
                    this.showPicker();
                });
            }
        }

        function removeTimeSlot(button) {
            const container = document.getElementById("timeSlotsContainer");
            const timeslots = container.children;
            
            if (timeslots.length > 1) {
                button.parentElement.remove();
                renumberTimeSlots();
            }
        }

        function renumberTimeSlots() {
            const timeslots = document.querySelectorAll("#timeSlotsContainer .time-slot-option");
            timeslots.forEach(function(timeslot, index) {
                timeslot.setAttribute("data-timeslot", index + 1);
            });
        }


        function createPoll(event) {
            event.preventDefault();
            
            if (validateForm()) {
                const pollData = collectPollData();
                submitPoll(pollData);
            }
        }

        function collectPollData() {
            return {
                title: document.getElementById("pollTitle").value.trim(),
                description: document.getElementById("pollDesc").value.trim(),
                type: document.getElementById("pollType").value,
                mode: document.getElementById("pollMode").value,
                options: collectTextOptions(),
                timeSettings: collectTimeSettings(),
                timeslots: collectCustomTimeslots()
            };
        }

        function collectTextOptions() {
            const options = [];
            document.querySelectorAll("#pollOptionsBox .poll-opt-input").forEach(function(input) {
                const value = input.value.trim();
                if (value) {
                    options.push(value);
                }
            });
            return options;
        }

        function collectTimeSettings() {
            const timeslots = [];
            document.querySelectorAll("#timeSlotsContainer .time-slot-option").forEach(function(timeslot) {
                const startTime = timeslot.querySelector(".time-start").value;
                const endTime = timeslot.querySelector(".time-end").value;
                if (startTime && endTime) {
                    const startTimestamp = new Date(startTime).getTime() / 1000;
                    const endTimestamp = new Date(endTime).getTime() / 1000;
                    timeslots.push({ 
                        startTime: startTimestamp, 
                        endTime: endTimestamp,
                        startTimeDisplay: startTime,
                        endTimeDisplay: endTime
                    });
                }
            });
            
            return {
                timeslots: timeslots,
                note: document.getElementById("timeSlotNote").value.trim()
            };
        }

        function collectCustomTimeslotSettings() {
            return {
                defenseMinutes: document.getElementById("defenseMinutesCustom").value,
                bufferMinutes: document.getElementById("bufferMinutesCustom").value,
                numberOfDefenses: document.getElementById("numberOfDefensesCustom").value,
                insertBreaks: document.getElementById("insertBreaksCustom").checked,
                howManyBreaks: document.getElementById("howManyBreaksCustom").value,
                breakMinutes: document.getElementById("breakMinutesCustom").value,
                note: document.getElementById("customNote").value.trim()
            };
        }

        function collectCustomTimeslots() {
            const defenseMinutes = parseInt(document.getElementById("defenseMinutesCustom").value) || 20;
            const bufferMinutes = parseInt(document.getElementById("bufferMinutesCustom").value) || 5;
            const numberOfDefenses = parseInt(document.getElementById("numberOfDefensesCustom").value) || 4;

            const slots = [];

            const pushDefenseSlots = (baseStartStr) => {
                const baseMs = Date.parse(baseStartStr);
                if (isNaN(baseMs)) return;
                let cursor = baseMs;
                for (let i = 0; i < numberOfDefenses; i++) {
                    const slotStartMs = cursor;
                    const slotEndMs = slotStartMs + defenseMinutes * 60 * 1000;
                    slots.push({
                        startTime: Math.floor(slotStartMs / 1000),
                        endTime: Math.floor(slotEndMs / 1000),
                        startTimeDisplay: new Date(slotStartMs).toISOString().slice(0,16),
                        endTimeDisplay: new Date(slotEndMs).toISOString().slice(0,16)
                    });
                    cursor = slotEndMs + bufferMinutes * 60 * 1000;
                }
            };

            const container = document.getElementById("customTimeslots");
            if (container && container.querySelectorAll(".custom-timeslot-option").length > 0) {
                container.querySelectorAll(".custom-timeslot-option").forEach((row) => {
                    const startStr = row.querySelector(".custom-time-start")?.value;
                    if (startStr) pushDefenseSlots(startStr);
                });
            } else {
                const startStr = document.getElementById("customStartTime")?.value;
                if (startStr) pushDefenseSlots(startStr);
            }

            return slots;
        }

        function validateForm() {
            const title = document.getElementById("pollTitle").value.trim();
            if (!title) {
                showModal(\'Validation Error\', \'Please enter a poll title\', \'warning\');
                return false;
            }
            
            const mode = document.getElementById("pollMode").value;
            switch(mode) {
                case "text":
                    return validateTextOptions();
                case "time":
                    return validateTimeOptions();
                case "custom_timeslot":
                    return validateCustomTimeslots();
                default:
                    return true;
            }
        }

        function validateTextOptions() {
            const options = collectTextOptions();
            if (options.length < 2) {
                showModal(\'Validation Error\', \'Please add at least 2 options\', \'warning\');
                return false;
            }
            return true;
        }

        function validateTimeOptions() {
            const timeslots = collectTimeSettings();
            if (timeslots.timeslots.length < 1) {
                showModal(\'Validation Error\', \'Please add at least 1 time slot\', \'warning\');
                return false;
            }
            return true;
        }

        function validateCustomTimeslots() {
            const timeslots = collectCustomTimeslots();
            if (timeslots.length < 1) {
                showModal(\'Validation Error\', \'Please add at least 1 timeslot\', \'warning\');
                return false;
            }
            return true;
        }

        function showModal(title, message, type = \'info\', autoClose = false) {
            const modal = document.getElementById(\'pollModal\');
            const modalTitle = document.getElementById(\'modalTitle\');
            const modalMessage = document.getElementById(\'modalMessage\');
            const modalIcon = document.getElementById(\'modalIcon\');
            
            modalTitle.textContent = title;
            modalMessage.textContent = message;
            
            switch(type) {
                case \'success\':
                    modalIcon.innerHTML = \'<svg width="48" height="48" viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>\';
                    modalIcon.className = \'modal-icon success\';
                    break;
                case \'error\':
                    modalIcon.innerHTML = \'<svg width="48" height="48" viewBox="0 0 24 24" fill="currentColor"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>\';
                    modalIcon.className = \'modal-icon error\';
                    break;
                case \'warning\':
                    modalIcon.innerHTML = \'<svg width="48" height="48" viewBox="0 0 24 24" fill="currentColor"><path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/></svg>\';
                    modalIcon.className = \'modal-icon warning\';
                    break;
                default:
                    modalIcon.innerHTML = \'<svg width="48" height="48" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>\';
                    modalIcon.className = \'modal-icon info\';
            }
            
            modal.classList.add(\'show\');
            
            if (autoClose) {
                setTimeout(() => {
                    closeModal();
                }, 3000);
            }
        }
        
        function closeModal() {
            const modal = document.getElementById(\'pollModal\');
            const modalBtn = document.getElementById(\'modalBtn\');
            const modalCancelBtn = document.getElementById(\'modalCancelBtn\');
            
            modalBtn.textContent = \'OK\';
            modalBtn.style.background = \'#0f6cbf\';
            modalBtn.onclick = closeModal;
            
            modalCancelBtn.style.display = \'none\';
            
            const modalContent = modal.querySelector(\'.modal-content\');
            if (modalContent) {
                modalContent.classList.remove(\'poll-results-modal\');
            }
            
            modal.classList.remove(\'show\');
        }
        
        document.addEventListener(\'click\', function(e) {
            const modal = document.getElementById(\'pollModal\');
            if (e.target === modal) {
                closeModal();
            }
        });

        function submitPoll(pollData) {
            console.log(\'Submitting poll data:\', pollData);
            
            const formData = new FormData();
            formData.append("action", "create_poll");
            formData.append("title", pollData.title);
            formData.append("description", pollData.description);
            formData.append("poll_type", pollData.type);
            formData.append("poll_mode", pollData.mode);
            if (window.M && M.cfg && M.cfg.sesskey) {
                formData.append(\'sesskey\', M.cfg.sesskey);
            }
            
            switch(pollData.mode) {
                case "text":
                    pollData.options.forEach((option, index) => {
                        formData.append("options[]", option);
                    });
                    break;
                case "time":
                    pollData.timeSettings.timeslots.forEach((timeslot, index) => {
                        formData.append("timeslots[" + index + "][start_time]", timeslot.startTime);
                        formData.append("timeslots[" + index + "][end_time]", timeslot.endTime);
                    });
                    if (pollData.timeSettings.note) {
                        formData.append("note", pollData.timeSettings.note);
                    }
                    break;
                case "custom_timeslot":
                    pollData.timeslots.forEach((timeslot, index) => {
                        formData.append("timeslots[" + index + "][start_time]", timeslot.startTime);
                        formData.append("timeslots[" + index + "][end_time]", timeslot.endTime);
                    });
                    const customSettings = collectCustomTimeslotSettings();
                    formData.append("defense_minutes", customSettings.defenseMinutes);
                    formData.append("buffer_minutes", customSettings.bufferMinutes);
                    formData.append("number_of_defenses", customSettings.numberOfDefenses);
                    formData.append("insert_breaks", customSettings.insertBreaks ? "1" : "0");
                    if (customSettings.insertBreaks) {
                        formData.append("how_many_breaks", customSettings.howManyBreaks);
                        formData.append("break_minutes", customSettings.breakMinutes);
                    }
                    if (customSettings.note) {
                        formData.append("note", customSettings.note);
                    }
                    break;
            }
            
            fetch(\'/blocks/poll/ajax_handler.php\', {
                method: "POST",
                body: formData
            })
            .then(response => {
                console.log(\'Response status:\', response.status);
                console.log(\'Response headers:\', response.headers);
                return response.text();
            })
            .then(text => {
                console.log(\'Raw response:\', text);
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        showModal(\'Poll Created!\', \'Your poll has been created successfully.\', \'success\', true);
                        setTimeout(() => {
                            location.reload();
                        }, 2000);
                    } else {
                        showModal(\'Error\', \'Error creating poll: \' + (data.message || \'Unknown error\'), \'error\');
                    }
                } catch (parseError) {
                    console.error(\'JSON parse error:\', parseError);
                    console.error(\'Raw response text:\', text);
                    if (text.includes(\'success\') && text.includes(\'true\')) {
                        showModal(\'Poll Created!\', \'Your poll has been created successfully.\', \'success\', true);
                        setTimeout(() => {
                            location.reload();
                        }, 2000);
                    } else {
                        showModal(\'Error\', \'Error creating poll. Please try again.\', \'error\');
                    }
                }
            })
            .catch(error => {
                console.error(\'Network error:\', error);
                showModal(\'Error\', \'Network error occurred. Please try again.\', \'error\');
            });
        }
        </script>';

        $content .= '<script>
        function resetForm() {
            const pollTitle = document.getElementById("pollTitle");
            const pollDesc = document.getElementById("pollDesc");
            const pollType = document.getElementById("pollType");
            const pollMode = document.getElementById("pollMode");
            
            if (pollTitle) pollTitle.value = "";
            if (pollDesc) pollDesc.value = "";
            if (pollType) pollType.value = "single";
            if (pollMode) pollMode.value = "text";
            
            const pollOptionsBox = document.getElementById("pollOptionsBox");
            if (pollOptionsBox) {
                pollOptionsBox.innerHTML = "";
                addPollOption();
                addPollOption();
            }
            
            const timeSlotsContainer = document.getElementById("timeSlotsContainer");
            if (timeSlotsContainer) {
                timeSlotsContainer.innerHTML = "";
                addTimeSlot();
                addTimeSlot();
            }
            
            const timeSlotNote = document.getElementById("timeSlotNote");
            
            if (timeSlotNote) timeSlotNote.value = "";
            
            pollModeChanged();
        }

        function initializeTimeInputs() {
            const startInputs = document.querySelectorAll("#timeSlotsContainer .time-start");
            const endInputs = document.querySelectorAll("#timeSlotsContainer .time-end");
            
            startInputs.forEach(input => {
                input.type = "datetime-local";
                input.addEventListener("click", function() {
                    this.showPicker();
                });
            });
            
            endInputs.forEach(input => {
                input.type = "datetime-local";
                input.addEventListener("click", function() {
                    this.showPicker();
                });
            });
        }
        
        function initializeCustomTimeslotInputs() {
            const customStartInput = document.getElementById("customStartTime");
            const customEndInput = document.getElementById("customEndTime");
            
            if (customStartInput) {
                customStartInput.type = "datetime-local";
                customStartInput.addEventListener("click", function() {
                    this.showPicker();
                });
            }
            
            if (customEndInput) {
                customEndInput.type = "datetime-local";
                customEndInput.readOnly = true;
            }
            
            const formFields = ["defenseMinutesCustom", "bufferMinutesCustom", "numberOfDefensesCustom", "howManyBreaksCustom", "breakMinutesCustom", "customNote"];
            formFields.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                if (field) {
                    field.addEventListener("input", updateCustomTimeslotPreview);
                    field.addEventListener("change", updateCustomTimeslotPreview);
                    
                    if (["defenseMinutesCustom", "bufferMinutesCustom", "numberOfDefensesCustom", "howManyBreaksCustom", "breakMinutesCustom"].includes(fieldId)) {
                        field.addEventListener("input", updateCustomEndTime);
                        field.addEventListener("change", updateCustomEndTime);
                    }
                }
            });
            
            const insertBreaksCheckbox = document.getElementById("insertBreaksCustom");
            if (insertBreaksCheckbox) {
                insertBreaksCheckbox.addEventListener("change", updateCustomTimeslotPreview);
            }
            
            updateCustomTimeslotPreview();
        }
        
        function updateCustomEndTime() {
            const startTime = document.getElementById("customStartTime").value;
            const setEndTimeManually = document.getElementById("setEndTimeManually").checked;
            const defenseMinutes = parseInt(document.getElementById("defenseMinutesCustom").value) || 20;
            const bufferMinutes = parseInt(document.getElementById("bufferMinutesCustom").value) || 5;
            const numberOfDefenses = parseInt(document.getElementById("numberOfDefensesCustom").value) || 4;
            
            if (startTime && !setEndTimeManually) {
                const startDate = new Date(startTime);
                let totalMinutes = (defenseMinutes * numberOfDefenses) + (bufferMinutes * (numberOfDefenses - 1));
                
                const insertBreaks = document.getElementById("insertBreaksCustom").checked;
                const howManyBreaks = parseInt(document.getElementById("howManyBreaksCustom").value) || 1;
                const breakMinutes = parseInt(document.getElementById("breakMinutesCustom").value) || 10;
                
                if (insertBreaks) {
                    totalMinutes += (howManyBreaks * breakMinutes);
                }
                
                const endDate = new Date(startDate.getTime() + (totalMinutes * 60 * 1000));
                
                document.getElementById("customEndTime").value = endDate.toISOString().slice(0, 16);
            }
            
            updateCustomTimeslotPreview();
        }
        
        function toggleEndTimeManual() {
            const setEndTimeManually = document.getElementById("setEndTimeManually").checked;
            const customEndInput = document.getElementById("customEndTime");
            
            if (setEndTimeManually) {
                customEndInput.readOnly = false;
                customEndInput.value = "";
            } else {
                customEndInput.readOnly = true;
                updateCustomEndTime();
            }
            
            updateCustomTimeslotPreview();
        }
        
        function calculateBreakDistribution(defenseMinutes, bufferMinutes, numberOfDefenses, howManyBreaks, breakMinutes) {
            const breaks = [];
            
            if (howManyBreaks <= 0) return breaks;
            
            const totalDefenseTime = defenseMinutes * numberOfDefenses;
            const totalBufferTime = bufferMinutes * (numberOfDefenses - 1);
            const totalWorkTime = totalDefenseTime + totalBufferTime;
            
            const defenseEnds = [];
            let currentTime = 0;
            
            for (let i = 0; i < numberOfDefenses; i++) {
                currentTime += defenseMinutes;
                defenseEnds.push(currentTime);
                if (i < numberOfDefenses - 1) {
                    currentTime += bufferMinutes;
                }
            }
            
            if (howManyBreaks <= numberOfDefenses - 1) {
                const breakInterval = Math.floor((numberOfDefenses - 1) / howManyBreaks);
                
                for (let i = 0; i < howManyBreaks; i++) {
                    const defenseIndex = (i + 1) * breakInterval;
                    if (defenseIndex < numberOfDefenses) {
                        const breakStartMinute = defenseEnds[defenseIndex - 1];
                        breaks.push({
                            startMinute: breakStartMinute,
                            duration: breakMinutes
                        });
                    }
                }
            } else {
                const totalIntervals = numberOfDefenses - 1;
                const intervalBetweenBreaks = Math.floor(totalWorkTime / (howManyBreaks + 1));
                
                for (let i = 0; i < howManyBreaks; i++) {
                    const breakStartMinute = Math.round(intervalBetweenBreaks * (i + 1));
                    breaks.push({
                        startMinute: breakStartMinute,
                        duration: breakMinutes
                    });
                }
            }
            
            return breaks;
        }
        
        function updateCustomTimeslotPreview() {
            const startTime = document.getElementById("customStartTime").value;
            const endTime = document.getElementById("customEndTime").value;
            const defenseMinutes = parseInt(document.getElementById("defenseMinutesCustom").value) || 20;
            const bufferMinutes = parseInt(document.getElementById("bufferMinutesCustom").value) || 5;
            const numberOfDefenses = parseInt(document.getElementById("numberOfDefensesCustom").value) || 4;
            const insertBreaks = document.getElementById("insertBreaksCustom").checked;
            const howManyBreaks = parseInt(document.getElementById("howManyBreaksCustom").value) || 1;
            const breakMinutes = parseInt(document.getElementById("breakMinutesCustom").value) || 10;
            const note = document.getElementById("customNote").value;
            
            const preview = document.getElementById("customTimeslotPreview");
            
            if (!startTime) {
                preview.textContent = getTranslatedText("Set parameters to see preview.");
                return;
            }
            
            let previewText = "";
            
            if (startTime && endTime) {
                const startDate = new Date(startTime);
                const endDate = new Date(endTime);
                const durationMinutes = Math.round((endDate - startDate) / (1000 * 60));
                
                // Format dates more clearly
                const startFormatted = startDate.toLocaleDateString() + " at " + startDate.toLocaleTimeString([], {hour: \'2-digit\', minute: \'2-digit\'});
                const endFormatted = endDate.toLocaleDateString() + " at " + endDate.toLocaleTimeString([], {hour: \'2-digit\', minute: \'2-digit\'});
                
                previewText = "Start: " + startFormatted + "\\n";
                previewText += "End: " + endFormatted + "\\n";
                previewText += "Duration: " + durationMinutes + " minutes\\n";
                previewText += "Defense time: " + defenseMinutes + " minutes\\n";
                previewText += "Buffer time: " + bufferMinutes + " minutes\\n";
                previewText += "Number of defenses: " + numberOfDefenses + "\\n";
                
                if (insertBreaks) {
                    previewText += "Breaks: " + howManyBreaks + " × " + breakMinutes + " minutes\\n";
                    previewText += "\\nBreak Distribution:\\n";
                    
                    // Calculate and show break distribution
                    const breakDistribution = calculateBreakDistribution(defenseMinutes, bufferMinutes, numberOfDefenses, howManyBreaks, breakMinutes);
                    breakDistribution.forEach((breakInfo, index) => {
                        const breakTime = new Date(startDate.getTime() + (breakInfo.startMinute * 60 * 1000));
                        const breakTimeFormatted = breakTime.toLocaleTimeString([], {hour: \'2-digit\', minute: \'2-digit\'});
                        previewText += "Break " + (index + 1) + ": " + breakTimeFormatted + " (" + breakInfo.startMinute + " min from start)\\n";
                    });
                }
                
                if (note) {
                    previewText += "\\nNote: " + note;
                }
            }
            
            // Use innerHTML to properly render line breaks with better formatting
            preview.innerHTML = \'<div style="line-height: 1.6; font-family: monospace;">\' + previewText.replace(/\\n/g, \'<br>\') + \'</div>\';
        }
        
        function toggleBreaksCustom() {
            const insertBreaks = document.getElementById("insertBreaksCustom").checked;
            const breaksOptions = document.getElementById("breaksOptionsCustom");
            
            if (insertBreaks) {
                breaksOptions.style.display = "block";
            } else {
                breaksOptions.style.display = "none";
            }
            
            updateCustomTimeslotPreview();
        }

        document.addEventListener("DOMContentLoaded", function() {
            pollTypeChanged();
            pollModeChanged();
            addPollOption();
            addPollOption();
        });
        </script>';


        $content .= '<div class="poll-results-section" style="margin: 20px 0; padding: 20px; background: #f8f9fa; border-radius: 8px;">
            <div class="poll-results-header" style="margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #dee2e6;">
                                    <h3 style="margin: 0; color: #333; font-size: 18px; font-weight: 600;" data-translate="Poll Results">Poll Results</h3>
                <div class="search-container" style="margin-top: 15px;">
                    <input type="text" id="searchPolls" class="search-input" placeholder="Search polls..." data-translate-placeholder="Search polls..." style="width: 100%; padding: 10px; border: 1px solid #dee2e6; border-radius: 4px; font-size: 14px;">
                </div>
            </div>
            
            <div class="poll-actions" style="margin: 20px 0; display: flex; gap: 10px; flex-wrap: wrap;">
                <button class="action-btn primary" onclick="exportAllPolls()" style="background: #007bff; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; font-weight: 500; display: flex; align-items: center; gap: 8px;">
                    <span class="btn-icon">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/>
                        </svg>
                    </span>
                    <span data-translate="Export All">Export All</span>
                </button>
                <button class="action-btn" onclick="exportSelectedPolls()" style="background: #6c757d; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; font-weight: 500; display: flex; align-items: center; gap: 8px;">
                    <span class="btn-icon">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/>
                        </svg>
                    </span>
                    <span data-translate="Export Selected">Export Selected</span> (<span id="selectedCount">0</span>)
                </button>


                <button class="action-btn" onclick="selectAllPolls()" style="background: #28a745; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; font-weight: 500; display: flex; align-items: center; gap: 8px;">
                    <span class="btn-icon">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                        </svg>
                    </span>
                    <span data-translate="Select All">Select All</span>
                </button>
                <button class="action-btn" onclick="deselectAllPolls()" style="background: #dc3545; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; font-weight: 500; display: flex; align-items: center; gap: 8px;">
                    <span class="btn-icon">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                        </svg>
                    </span>
                    <span data-translate="Deselect All">Deselect All</span>
                </button>
                <button class="action-btn" onclick="deleteSelectedPolls()" style="background: #dc3545; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; font-weight: 500; display: flex; align-items: center; gap: 8px;">
                    <span class="btn-icon">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/>
                        </svg>
                    </span>
                    <span data-translate="Delete Selected">Delete Selected</span> (<span id="deleteCount">0</span>)
                </button>
            </div>
            
            <div class="polls-list" id="pollsList" style="max-height: none; overflow-y: visible; height: auto;">
                <div class="loading-spinner" id="pollsLoader" style="text-align: center; padding: 40px 20px; color: #6c757d;">
                    <div class="spinner" style="display: inline-block; width: 40px; height: 40px; border: 4px solid #f3f3f3; border-top: 4px solid #0f6cbf; border-radius: 50%; animation: spin 1s linear infinite; margin-bottom: 20px;"></div>
                    <p data-translate="Loading polls...">Loading polls...</p>
                </div>
                <!-- Polls will be loaded dynamically -->
            </div>
        </div>';


        $content .= '<div class="non-voting-section">
            <div class="non-voting-header">
                <h3 data-translate="Non-Voting Professors">Non-Voting Professors</h3>
                        <div class="header-actions">
            <button class="collapse-btn" onclick="toggleNonVotingSection()" data-translate="Collapse">Collapse</button>
        </div>
            </div>
            
            <div class="non-voting-content" id="nonVotingContent">
                <div class="loading-spinner" id="nonVotingLoader">
                    <div class="spinner"></div>
                    <p data-translate="Loading poll statistics...">Loading poll statistics...</p>
                </div>
                
                <div class="non-voting-grid" id="nonVotingGrid" style="display: none;">
                    <!-- Poll cards will be loaded dynamically -->
                </div>
                
                <div class="overall-statistics" id="overallStatistics" style="display: none;">
                    <h4 data-translate="Overall Voting Statistics">Overall Voting Statistics</h4>
                    <div class="stats-summary" id="statsSummary" data-translate="Loading statistics...">Loading statistics...</div>
                    
                    <div class="chart-container">
                        <div class="bar-chart" id="barChart">
                            <!-- Chart bars will be loaded dynamically -->
                        </div>
                    </div>
                    
                    <div class="total-counts" id="totalCounts" data-translate="Loading counts...">Loading counts...</div>
                </div>
            </div>
        </div>';

        $content .= '<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>';

        $content .= '<script>


        function viewPollResults(pollId) {
            console.log(\'viewPollResults called with ID:\', pollId);
            
            showModal(\'Loading...\', \'Fetching poll results...\', \'info\');
            
            const formData = new FormData();
            formData.append("action", "get_poll_results");
            formData.append("poll_id", pollId);
            if (window.M && M.cfg && M.cfg.sesskey) {
                formData.append(\'sesskey\', M.cfg.sesskey);
            }
            
            console.log(\'Sending request to ajax_handler.php\');
            
            fetch(\'/blocks/poll/ajax_handler.php\', {
                method: "POST",
                body: formData
            })
            .then(response => {
                console.log(\'Response received:\', response);
                return response.json();
            })
            .then(data => {
                console.log(\'Data received:\', data);
                if (data.success) {
                    console.log(\'Calling showDetailedPollResults...\');
                    closeModal();
                    showDetailedPollResults(data);
                } else {
                    closeModal();
                    showModal(\'Error\', \'Error loading poll results: \' + (data.message || \'Unknown error\'), \'error\');
                }
            })
            .catch(error => {
                console.error(\'Poll results error:\', error);
                closeModal();
                showModal(\'Error\', \'Network error occurred while loading poll results.\', \'error\');
            });
        }
        
        function showDetailedPollResults(data) {
            console.log(\'showDetailedPollResults called with data:\', data);
            
            const modal = document.getElementById(\'pollModal\');
            const modalTitle = document.getElementById(\'modalTitle\');
            const modalMessage = document.getElementById(\'modalMessage\');
            const modalIcon = document.getElementById(\'modalIcon\');
            const modalBtn = document.getElementById(\'modalBtn\');
            const modalCancelBtn = document.getElementById(\'modalCancelBtn\');
            
            console.log(\'Modal elements found:\', {
                modal: !!modal,
                modalTitle: !!modalTitle,
                modalMessage: !!modalMessage,
                modalIcon: !!modalIcon,
                modalBtn: !!modalBtn,
                modalCancelBtn: !!modalCancelBtn
            });
            
            modalTitle.textContent = data.poll.title + \' - Poll Results\';
            modalIcon.style.display = \'none\';
            modalBtn.textContent = \'Close\';
            modalBtn.onclick = closeModal;
            modalCancelBtn.style.display = \'none\';
            
            modal.querySelector(\'.modal-content\').classList.add(\'poll-results-modal\');
            
            let resultsHTML = \'<div class="detailed-poll-results">\';
            
            resultsHTML += \'<div class="vote-distribution">\';
            
            resultsHTML += \'<div class="pie-chart-placeholder">\';
            resultsHTML += \'<div class="chart-container">\';
            resultsHTML += \'<div class="chart-info">\';
            
            resultsHTML += \'<div class="bar-chart-container">\';
            
            const colors = [\'#dc3545\', \'#6f42c1\', \'#0d6efd\', \'#fd7e14\', \'#20c997\', \'#0dcaf0\', \'#ffc107\', \'#198754\'];
            
            // Calculate optimal bar width based on number of options for domino effect
            const totalOptions = data.options.length;
            const maxBarWidth = 50;
            const minBarWidth = 25;
            const overlapAmount = 15; // How much bars overlap
            const effectiveBarWidth = maxBarWidth - overlapAmount;
            const containerWidth = Math.min(600, (totalOptions * effectiveBarWidth) + overlapAmount + 40);
            
            resultsHTML += \'<div class="dynamic-chart domino-chart" style="width: \' + containerWidth + \'px; display: flex; gap: 0; align-items: flex-end; justify-content: center; margin: 0 auto; position: relative;">\';
            
            data.options.forEach((option, index) => {
                const color = colors[index % colors.length];
                const percentage = data.option_votes[option.id] ? data.option_votes[option.id].percentage : 0;
                const barHeight = Math.max(percentage * 1.5, 20);
                const barWidth = maxBarWidth;
                const zIndex = totalOptions - index; // Higher bars have higher z-index
                
                resultsHTML += \'<div class="bar-chart-bar domino-bar" style="width: \' + barWidth + \'px; min-width: \' + barWidth + \'px; z-index: \' + zIndex + \'; margin-left: \' + (index === 0 ? 0 : -overlapAmount) + \'px;">\';
                resultsHTML += \'<div class="bar domino-bar-inner" style="height: \' + barHeight + \'px; background: \' + color + \'; width: 100%; border-radius: 4px 4px 0 0; box-shadow: 0 2px 8px rgba(0,0,0,0.15);">\';
                resultsHTML += \'</div>\';
                resultsHTML += \'<div class="bar-label" style="font-size: 9px; color: #666; text-align: center; margin-top: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">\' + (index + 1) + \'</div>\';
                resultsHTML += \'</div>\';
            });
            
            resultsHTML += \'</div>\';
            
            resultsHTML += \'</div>\';
            resultsHTML += \'</div>\';
            
            resultsHTML += \'<div class="chart-legend">\';
            
            data.options.forEach((option, index) => {
                const color = colors[index % colors.length];
                const percentage = data.option_votes[option.id] ? data.option_votes[option.id].percentage : 0;
                const optionText = option.start_time ? formatDateTime(option.start_time) : (option.option_text || \'Option \' + (index + 1));
                const truncatedText = optionText.length > 25 ? optionText.substring(0, 22) + \'...\' : optionText;
                
                resultsHTML += \'<div class="legend-item" style="border-left-color: \' + color + \'">\';
                resultsHTML += \'<span class="legend-color" style="background: \' + color + \'"></span>\';
                resultsHTML += \'<span class="legend-text" title="\' + optionText + \'">\' + truncatedText + \'</span>\';
                resultsHTML += \'<span class="legend-percentage">\' + percentage + \'%</span>\';
                resultsHTML += \'</div>\';
            });
            
            resultsHTML += \'</div>\';
            resultsHTML += \'<p class="poll-summary">\' + data.total_professors + \' \' + getTranslatedText(\'participants\') + \' • \' + data.options.length + \' \' + getTranslatedText(\'options\') + \'</p>\';
            resultsHTML += \'</div>\';
            
            resultsHTML += \'<div class="professor-selection-grid">\';
            resultsHTML += \'<div class="grid-header">\';
            resultsHTML += \'<div class="search-section">\';
            resultsHTML += \'<input type="text" class="professor-search" placeholder="\' + getTranslatedText(\'Search professor...\') + \'" onkeyup="filterProfessors(this.value)">\';
            resultsHTML += \'</div>\';
            resultsHTML += \'</div>\';
            
            resultsHTML += \'<div class="grid-table-container" style="overflow-x: scroll; overflow-y: auto; max-width: 100%; -webkit-overflow-scrolling: touch; position: relative; border: 1px solid #e9ecef; border-radius: 8px;">\';
            resultsHTML += \'<table class="professor-grid" style="width: max-content; min-width: 100%; border-collapse: separate; border-spacing: 0;">\';
            
                            resultsHTML += \'<thead><tr><th class="professor-column">\' + getTranslatedText(\'Professor\') + \'</th>\';
            data.options.forEach((option, index) => {
                const color = colors[index % colors.length];
                resultsHTML += \'<th class="option-column" style="border-left: 3px solid \' + color + \'">\' + (option.start_time ? formatDateTime(option.start_time) : (option.option_text || \'Option \' + (index + 1))) + \'</th>\';
            });
            resultsHTML += \'</tr></thead>\';
            resultsHTML += \'<tbody>\';
            data.professors.forEach(professor => {
                resultsHTML += \'<tr class="professor-row" data-professor="\' + professor.firstname.toLowerCase() + \' \' + professor.lastname.toLowerCase() + \'">\';
                resultsHTML += \'<td class="professor-name">\';
                resultsHTML += \'<span class="professor-avatar"></span>\';
                resultsHTML += professor.firstname + \' \' + professor.lastname;
                resultsHTML += \'</td>\';
                
                data.options.forEach(option => {
                    const hasVoted = data.professor_voting_status[professor.id] && data.professor_voting_status[professor.id][option.id];
                    const cellClass = hasVoted ? \'voted-cell\' : \'empty-cell\';
                    resultsHTML += \'<td class="\' + cellClass + \'">\';
                    if (hasVoted) {
                        resultsHTML += \'<span class="vote-indicator"></span>\';
                    }
                    resultsHTML += \'</td>\';
                });
                
                resultsHTML += \'</tr>\';
            });
            resultsHTML += \'</tbody></table></div>\';
            resultsHTML += \'</div>\';
            
            resultsHTML += \'</div>\';
            
            modalMessage.innerHTML = resultsHTML;
            modal.classList.add(\'show\');
            
            setTimeout(() => {
                const chartContainer = document.querySelector(\'.bar-chart-container\');
                if (chartContainer) {
                    chartContainer.scrollLeft = 0;
                    console.log(\'Chart container positioned to show all bars\');
                }
                
                const tableContainer = document.querySelector(\'.grid-table-container\');
                const table = document.querySelector(\'.professor-grid\');
                
                if (tableContainer && table) {
                    console.log(\'Found table elements, setting up horizontal scrolling...\');
                    
                    table.style.width = \'max-content\';
                    table.style.minWidth = \'calc(100% + 200px)\';
                    table.style.tableLayout = \'auto\';
                    
                    tableContainer.style.overflowX = \'scroll\';
                    tableContainer.style.overflowY = \'auto\';
                    
                    tableContainer.style.whiteSpace = \'nowrap\';
                    tableContainer.style.wordWrap = \'normal\';
                    
                    console.log(\'Initial - Table width:\', table.scrollWidth, \'Container width:\', tableContainer.clientWidth);
                    
                    table.offsetHeight;
                    tableContainer.offsetHeight;
                    
                    console.log(\'After reflow - Table width:\', table.scrollWidth, \'Container width:\', tableContainer.clientWidth);
                    
                    if (table.scrollWidth > tableContainer.clientWidth) {
                        tableContainer.style.borderRight = \'3px solid #007bff\';
                        console.log(\'Content overflows - scrollbar should be visible\');
                    } else {
                        console.log(\'Content does not overflow - forcing table to be wider\');
                        table.style.minWidth = \'calc(100% + 300px)\';
                        table.style.width = \'calc(100% + 300px)\';
                    }
                    
                    const optionColumns = table.querySelectorAll(\'th.option-column, td.option-column\');
                    optionColumns.forEach(col => {
                        col.style.minWidth = \'100px\';
                        col.style.width = \'100px\';
                        col.style.maxWidth = \'100px\';
                        col.style.flexShrink = \'0\';
                    });
                    
                    setTimeout(() => {
                        console.log(\'Final - Table width:\', table.scrollWidth, \'Container width:\', tableContainer.clientWidth);
                        console.log(\'Container overflow-x:\', tableContainer.style.overflowX);
                        console.log(\'Container computed overflow-x:\', window.getComputedStyle(tableContainer).overflowX);
                    }, 50);
                } else {
                    console.error(\'Table elements not found:\', {tableContainer, table});
                }
            }, 100);
            window.currentPollResults = data;
        }
        
        function filterProfessors(searchTerm) {
            const rows = document.querySelectorAll(\'.professor-row\');
            const searchLower = searchTerm.toLowerCase();
            
            rows.forEach(row => {
                const professorName = row.getAttribute(\'data-professor\');
                if (professorName.includes(searchLower)) {
                    row.style.display = \'table-row\';
                } else {
                    row.style.display = \'none\';
                }
            });
        }
        
        function formatDateTime(timestamp) {
            const date = new Date(timestamp * 1000);
            const day = date.getDate();
            const month = date.toLocaleDateString(\'en-US\', { month: \'short\' });
            const time = date.toLocaleTimeString([], {hour: \'2-digit\', minute:\'2-digit\'});
            return month + \' \' + day + \' \' + time;
        }

        function deletePoll(pollId) {
            window.pendingDeletePollId = pollId;
            showDeleteConfirmationModal();
        }
        
        function showDeleteConfirmationModal(pollIds, pollTitles) {
            const modal = document.getElementById(\'pollModal\');
            const modalTitle = document.getElementById(\'modalTitle\');
            const modalMessage = document.getElementById(\'modalMessage\');
            const modalIcon = document.getElementById(\'modalIcon\');
            const modalBtn = document.getElementById(\'modalBtn\');
            const modalCancelBtn = document.getElementById(\'modalCancelBtn\');
            
            // Store the poll IDs and titles for later use
            window.pendingDeletePollIds = pollIds;
            window.pendingDeletePollTitles = pollTitles;
            
            const isMultiple = pollIds.length > 1;
            const pollText = isMultiple ? \'polls\' : \'poll\';
            
            modalTitle.textContent = \'Confirm Deletion\';
            
            // Create a more detailed message with poll titles
            let messageHTML = `<div style="text-align: left; margin-bottom: 20px;">
                <p style="margin: 0 0 15px 0; font-weight: 600; color: #dc3545;">⚠️ This action cannot be undone!</p>
                <p style="margin: 0 0 15px 0;">Are you sure you want to delete ${pollIds.length} ${pollText}?</p>`;
            if (isMultiple) {
                messageHTML += `<div style="background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 6px; padding: 15px; margin: 15px 0;">
                    <p style="margin: 0 0 10px 0; font-weight: 600; color: #495057;">Polls to be deleted:</p>
                    <ul style="margin: 0; padding-left: 20px; color: #666;">
                        ${pollTitles.map(title => `<li>${title}</li>`).join(\'\')}
                    </ul>
                </div>`;
            } else {
                messageHTML += `<div style="background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 6px; padding: 15px; margin: 15px 0;">
                    <p style="margin: 0 0 10px 0; font-weight: 600; color: #495057;">Poll to be deleted:</p>
                    <p style="margin: 0; color: #666; font-weight: 500;">${pollTitles[0]}</p>
                </div>`;
            }
            
            messageHTML += \'</div>\';
            
            modalMessage.innerHTML = messageHTML;
            modalIcon.innerHTML = \'<svg width="48" height="48" viewBox="0 0 24 24" fill="currentColor"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>\';
            modalIcon.className = \'modal-icon warning\';
            
            modalBtn.textContent = \'Delete\';
            modalBtn.style.background = \'#dc3545\';
            modalBtn.onclick = confirmDeletePoll;
            
            modalCancelBtn.textContent = \'Cancel\';
            modalCancelBtn.style.display = \'inline-block\';
            modalCancelBtn.onclick = function() {
                closeModal();
                window.pendingDeletePollIds = null;
                window.pendingDeletePollTitles = null;
            };
            
            modal.classList.add(\'show\');
        }
        
        function confirmDeletePoll() {
            const pollIds = window.pendingDeletePollIds;
            if (!pollIds || pollIds.length === 0) return;
            
            closeModal();
            
            deletePolls(pollIds);
            
            // Clear the stored data
            window.pendingDeletePollIds = null;
            window.pendingDeletePollTitles = null;
        }
        


        document.addEventListener(\'DOMContentLoaded\', function() {
            console.log(\'DOM loaded, attempting to load poll statistics...\');
            try {
                loadPollStatistics();
            } catch (error) {
                console.error(\'Error in DOMContentLoaded:\', error);
            }
            
            const searchInput = document.getElementById(\'searchPolls\');
            if (searchInput) {
                searchInput.addEventListener(\'input\', function() {
                    const searchTerm = this.value.toLowerCase();
                    const pollItems = document.querySelectorAll(\'.poll-item\');
                    
                    pollItems.forEach(item => {
                        const title = item.querySelector(\'.poll-title\').textContent.toLowerCase();
                        const description = item.querySelector(\'.poll-description\').textContent.toLowerCase();
                        
                        if (title.includes(searchTerm) || description.includes(searchTerm)) {
                            item.style.display = \'flex\';
                        } else {
                            item.style.display = \'none\';
                        }
                    });
                });
            }
            
        const pollsList = document.querySelector(\'.polls-list\');
        if (pollsList) {
            const checkScrollIndicator = () => {
                if (pollsList.scrollHeight > pollsList.clientHeight) {
                    pollsList.style.borderRight = \'2px solid #007bff\';
                    pollsList.style.boxShadow = \'inset 0 0 0 1px #007bff\';
                } else {
                    pollsList.style.borderRight = \'1px solid transparent\';
                    pollsList.style.boxShadow = \'none\';
                }
            };
            
            checkScrollIndicator();
            window.addEventListener(\'resize\', checkScrollIndicator);
            
            pollsList.addEventListener(\'scroll\', function() {
                this.style.scrollBehavior = \'smooth\';
            });
            
            pollsList.style.webkitOverflowScrolling = \'touch\';
        }
        
        loadPollResults();
    });
        

        

        
        function createSummaryWorksheet(pollIds, processedPolls, failedPolls) {
            const worksheetData = [];
            
            worksheetData.push([\'Export Summary\']);
            worksheetData.push([]);
            
            worksheetData.push([\'Export Date\', new Date().toLocaleString()]);
            worksheetData.push([\'Total Polls Requested\', pollIds.length]);
            worksheetData.push([\'Successfully Exported\', processedPolls]);
            worksheetData.push([\'Failed to Export\', failedPolls]);
            worksheetData.push([\'Success Rate\', `${Math.round((processedPolls / pollIds.length) * 100)}%`]);
            worksheetData.push([]);
            
            worksheetData.push([\'Exported Polls\']);
            worksheetData.push([\'Poll ID\', \'Poll Title\', \'Status\']);
            
            // Get poll titles from the DOM for better identification
            const pollItems = document.querySelectorAll(\'.poll-item\');
            pollIds.forEach(pollId => {
                const pollItem = Array.from(pollItems).find(item => 
                    item.getAttribute(\'data-poll-id\') === pollId
                );
                const title = pollItem ? (pollItem.querySelector(\'.poll-title\')?.textContent || \'Unknown\') : \'Unknown\';
                const status = \'Exported\';
                worksheetData.push([pollId, title, status]);
            });
            worksheetData.push([]);
            
            worksheetData.push([\'Instructions\']);
            worksheetData.push([\'1. Each poll has its own worksheet with descriptive names"\']);
            worksheetData.push([\'2. The Summary worksheet contains this overview"\']);
            worksheetData.push([\'3. Each poll worksheet includes voting matrix and statistics"\']);
            worksheetData.push([\'4. Use filters and sorting to analyze the data"\']);
            
            const worksheet = XLSX.utils.aoa_to_sheet(worksheetData);
            
            worksheet[\'!cols\'] = [
                { wch: 40 },
                { wch: 20 },
                { wch: 50 }
            ];
            
            const range = XLSX.utils.decode_range(worksheet[\'!ref\']);
            
            const headerAddress = XLSX.utils.encode_cell({ r: 0, c: 0 });
            if (worksheet[headerAddress]) {
                worksheet[headerAddress].s = {
                    font: { bold: true, size: 16, color: { rgb: "FFFFFF" } },
                    fill: { fgColor: { rgb: "0F6CBF" } },
                    alignment: { horizontal: "center" }
                };
            }
            
            
            for (let C = 0; C < 3; C++) {
                const address = XLSX.utils.encode_cell({ r: 8, c: C });
                if (worksheet[address]) {
                    worksheet[address].s = {
                    font: { bold: true, size: 12, color: { rgb: "FFFFFF" } },
                    fill: { fgColor: { rgb: "28A745" } },
                    alignment: { horizontal: "center" }
                };
                }
            }
            
            const instructionsHeaderAddress = XLSX.utils.encode_cell({ r: 10 + pollIds.length, c: 0 });
            if (worksheet[instructionsHeaderAddress]) {
                worksheet[instructionsHeaderAddress].s = {
                    font: { bold: true, size: 12, color: { rgb: "FFC107" } },
                    alignment: { horizontal: "center" }
                };
            }
            
            return worksheet;
        }
        
        function loadPollResults() {
            console.log("Loading poll results...");
            const formData = new FormData();
            formData.append("action", "get_poll_statistics");
            if (window.M && M.cfg && M.cfg.sesskey) {
                formData.append(\'sesskey\', M.cfg.sesskey);
            }
            
            fetch("/blocks/poll/ajax_handler.php", {
                method: "POST",
                body: formData
            })
            .then(response => {
                console.log("Response status:", response.status);
                return response.json();
            })
            .then(data => {
                console.log("Response data:", data);
                if (data.success) {
                    console.log("Poll results loaded successfully. Polls found:", data.polls.length);
                    renderPollResults(data.polls);
                } else {
                    console.error("Error loading poll results:", data.message);
                    showModal("Error", "Error loading poll results: " + (data.message || "Unknown error"), "error");
                }
            })
            .catch(error => {
                console.error("Poll results error:", error);
                showModal("Error", "Network error occurred while loading poll results.", "error");
            });
        }
        
        function updateSelectedCount() {
            const checkboxes = document.querySelectorAll(\'.poll-select:checked\');
            const selectedCount = document.getElementById(\'selectedCount\');
            const deleteCount = document.getElementById(\'deleteCount\');
            
            if (selectedCount) {
                selectedCount.textContent = checkboxes.length;
            }
            if (deleteCount) {
                deleteCount.textContent = checkboxes.length;
            }
        }
        
        function selectAllPolls() {
            const checkboxes = document.querySelectorAll(\'.poll-select\');
            checkboxes.forEach(checkbox => {
                checkbox.checked = true;
            });
            updateSelectedCount();
        }
        
        function deselectAllPolls() {
            const checkboxes = document.querySelectorAll(\'.poll-select\');
            checkboxes.forEach(checkbox => {
                checkbox.checked = false;
            });
            updateSelectedCount();
        }

        
        function exportAllPolls() {
            const checkboxes = document.querySelectorAll(\'.poll-select\');
            if (checkboxes.length === 0) {
                showModal("Export", "No polls available to export", "warning");
                return;
            }
            
            const pollItems = document.querySelectorAll(\'.poll-item\');
            const pollIds = Array.from(pollItems).map(item => 
                item.getAttribute(\'data-poll-id\')
            );
            
            exportDetailedPollsToExcel(pollIds, \'all_polls\');
        }
        
        function exportSelectedPolls() {
            const checkboxes = document.querySelectorAll(\'.poll-select:checked\');
            if (checkboxes.length === 0) {
                showModal("Export", "Please select polls to export", "warning");
                return;
            }
            
            const selectedPollItems = Array.from(checkboxes).map(checkbox => 
                checkbox.closest(\'.poll-item\')
            ).filter(item => item !== null);
            
            const pollIds = selectedPollItems.map(item => 
                item.getAttribute(\'data-poll-id\')
            );
            
            exportDetailedPollsToExcel(pollIds, \'selected_polls\');
        }
        
        function exportSinglePoll(pollId) {
            exportDetailedPollsToExcel([pollId], \'single_poll\');
        }
        
        function deleteSelectedPolls() {
            const checkboxes = document.querySelectorAll(\'.poll-select:checked\');
            if (checkboxes.length === 0) {
                showModal("Delete", "Please select polls to delete", "warning");
                return;
            }
            
            const selectedPollItems = Array.from(checkboxes).map(checkbox => 
                checkbox.closest(\'.poll-item\')
            ).filter(item => item !== null);
            
            const pollIds = selectedPollItems.map(item => 
                item.getAttribute(\'data-poll-id\')
            );
            
            const pollTitles = selectedPollItems.map(item => {
                const titleElement = item.querySelector(\'.poll-title\');
                return titleElement ? titleElement.textContent : \'Unknown Poll\';
            });
            
            
            showDeleteConfirmationModal(pollIds, pollTitles);
        }
        
        function deletePolls(pollIds) {
            showModal("Deleting Polls", `Deleting ${pollIds.length} poll(s)...`, "info");
            
            let deletedCount = 0;
            let failedCount = 0;
            
            pollIds.forEach((pollId, index) => {
                const formData = new FormData();
                formData.append("action", "delete_poll");
                formData.append("poll_id", pollId);
                if (window.M && M.cfg && M.cfg.sesskey) {
                    formData.append(\'sesskey\', M.cfg.sesskey);
                }
                
                fetch("/blocks/poll/ajax_handler.php", {
                    method: "POST",
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        deletedCount++;
                        // Remove the poll item from the DOM
                        const pollItem = document.querySelector(`[data-poll-id="${pollId}"]`);
                        if (pollItem) {
                            pollItem.remove();
                        }
                    } else {
                        failedCount++;
                        console.error(`Failed to delete poll ${pollId}:`, data.message);
                    }
                    
                    // Update progress
                    const modalMessage = document.getElementById(\'modalMessage\');
                    if (modalMessage) {
                        const progress = Math.round(((deletedCount + failedCount) / pollIds.length) * 100);
                        modalMessage.textContent = `Progress: ${deletedCount + failedCount} of ${pollIds.length} (${progress}%)\\n\\nDeleted: ${deletedCount}\\nFailed: ${failedCount}`;
                    }
                    
                    // Check if all polls have been processed
                    if (deletedCount + failedCount === pollIds.length) {
                        closeModal();
                        
                        if (failedCount === 0) {
                            showModal("Success", `Successfully deleted ${deletedCount} poll(s)!`, "success", true);
                        } else {
                            showModal("Partial Success", `Deleted ${deletedCount} poll(s), but ${failedCount} failed. Check console for details.`, "warning", true);
                        }
                        
                        // Refresh the poll list
                        setTimeout(() => {
                            loadPollStatistics();
                        }, 2000);
                    }
                })
                .catch(error => {
                    failedCount++;
                    console.error(`Error deleting poll ${pollId}:`, error);
                    
                    const modalMessage = document.getElementById(\'modalMessage\');
                    if (modalMessage) {
                        const progress = Math.round(((deletedCount + failedCount) / pollIds.length) * 100);
                        modalMessage.textContent = `Progress: ${deletedCount + failedCount} of ${pollIds.length} (${progress}%)\\n\\nDeleted: ${deletedCount}\\nFailed: ${failedCount}`;
                    }
                    
                    if (deletedCount + failedCount === pollIds.length) {
                closeModal();
                        showModal("Partial Success", `Deleted ${deletedCount} poll(s), but ${failedCount} failed. Check console for details.`, "warning", true);
                        
                        setTimeout(() => {
                            loadPollStatistics();
                        }, 2000);
                    }
                });
            });
        }
        
        function deletePoll(pollId) {
            const pollItem = document.querySelector(`[data-poll-id="${pollId}"]`);
            const titleElement = pollItem ? pollItem.querySelector(\'.poll-title\') : null;
            const pollTitle = titleElement ? titleElement.textContent : \'Unknown Poll\';
            
            // Show custom Moodle-themed confirmation modal
            showDeleteConfirmationModal([pollId], [pollTitle]);
        }
        
        function exportDetailedPollsToExcel(pollIds, filename) {
            const pollCount = pollIds.length;
            const modalMessage = `Preparing detailed Excel export for ${pollCount} polls...\\n\\nEach poll will be exported to a separate worksheet with:\\n• Poll information and statistics\\n• Voting matrix (professors, options, and votes)\\n• Detailed voting breakdown (professor and option)\\n\\nFiles will be saved with descriptive names (no timestamps).`;
            showModal("Export", modalMessage, "info");
            
            const workbook = XLSX.utils.book_new();
            let processedPolls = 0;
            let failedPolls = 0;
            
            pollIds.forEach((pollId, index) => {
                fetchDetailedPollData(pollId).then(pollData => {
                    if (pollData) {
                        const worksheet = createPollWorksheet(pollData);
                        
                        // Create a better sheet name with poll title and ID
                        const pollTitle = pollData.poll.title || \'Unknown Poll\';
                        const cleanTitle = pollTitle.replace(/[<>:"/\\|?*]/g, \'_\').substring(0, 25); // Excel sheet name limit is 31 chars
                        const sheetName = `${cleanTitle}_${pollId}`;
                        
                        XLSX.utils.book_append_sheet(workbook, worksheet, sheetName);
                        
                        processedPolls++;
                        
                        const progress = Math.round((processedPolls / pollIds.length) * 100);
                        const modalMessage = document.getElementById(\'modalMessage\');
                        if (modalMessage) {
                            const pollTitle = pollData.poll.title || \'Unknown Poll\';
                            modalMessage.textContent = `Processed: ${pollTitle} (${pollId})\\nProgress: ${processedPolls} of ${pollIds.length} (${progress}%)`;
                        }
                        
                        if (processedPolls + failedPolls === pollIds.length) {
                            const summaryWorksheet = createSummaryWorksheet(pollIds, processedPolls, failedPolls);
                            XLSX.utils.book_append_sheet(workbook, summaryWorksheet, \'Summary\');
                            
                            let finalFilename;
                            
                            if (filename === \'all_polls\') {
                                finalFilename = \'All_Polls.xlsx\';
                            } else if (filename === \'selected_polls\') {
                                finalFilename = \'Selected_Polls.xlsx\';
                            } else if (filename === \'single_poll\') {
                                // Single poll export - use poll title only
                                const pollTitle = pollData.poll.title || \'Unknown_Poll\';
                                const cleanTitle = pollTitle.replace(/[<>:"/\\|?*]/g, \'_\').substring(0, 50);
                                finalFilename = `${cleanTitle}.xlsx`;
                            } else {
                                // Fallback for unknown export types
                                finalFilename = \'Poll_Export.xlsx\';
                            }
                            
                            XLSX.writeFile(workbook, finalFilename);
                            
                            closeModal();
                            
                            if (failedPolls > 0) {
                                showModal("Export Completed", `Exported ${processedPolls} polls successfully. ${failedPolls} polls failed. File: ${finalFilename}`, "warning");
                            } else {
                                showModal("Export Successful", `Exported ${processedPolls} detailed polls to ${finalFilename}`, "success");
                            }
                        }
                    }
                }).catch(error => {
                    console.error(`Error fetching data for poll ${pollId}:`, error);
                    failedPolls++;
                    
                    const modalMessage = document.getElementById(\'modalMessage\');
                    if (modalMessage) {
                        modalMessage.textContent = `Failed to fetch poll ${pollId}: ${error.message}. Continuing with other polls...`;
                    }
                    
                    if (processedPolls + failedPolls === pollIds.length) {
                        closeModal();
                        
                        if (processedPolls > 0) {
                            let finalFilename;
                            
                            if (filename === \'all_polls\') {
                                finalFilename = \'All_Polls.xlsx\';
                            } else if (filename === \'selected_polls\') {
                                finalFilename = \'Selected_Polls.xlsx\';
                            } else if (filename === \'single_poll\') {
                                finalFilename = \'Single_Poll.xlsx\';
                            } else {
                                finalFilename = \'Poll_Export.xlsx\';
                            }
                            
                            XLSX.writeFile(workbook, finalFilename);
                            showModal("Export Completed", `Exported ${processedPolls} polls successfully. ${failedPolls} polls failed due to API issues. File: ${finalFilename}`, "warning");
                        } else {
                            console.log("All polls failed, trying fallback export...");
                            console.log("Common failure reasons: Permission denied, Invalid poll data, Network issues");
                            console.log("Check browser console and server logs for detailed error information");
                            tryFallbackExport(pollIds, filename);
                        }
                    }
                });
            });
        }
        
        function tryFallbackExport(pollIds, filename) {
            console.log("Attempting fallback export...");
            
            const pollItems = document.querySelectorAll(\'.poll-item\');
            const basicPollData = [];
            
            pollIds.forEach(pollId => {
                const pollItem = Array.from(pollItems).find(item => 
                    item.getAttribute(\'data-poll-id\') === pollId
                );
                
                if (pollItem) {
                    const title = pollItem.querySelector(\'.poll-title\')?.textContent || \'Unknown\';
                    const description = pollItem.querySelector(\'.poll-description\')?.textContent || \'No description\';
                    const tags = Array.from(pollItem.querySelectorAll(\'.poll-tag\')).map(tag => tag.textContent);
                    const voters = pollItem.querySelector(\'.poll-tag.voters\')?.textContent || \'0\';
                    
                    basicPollData.push({
                        id: pollId,
                        title: title,
                        description: description,
                        poll_type: tags[0] || \'N/A\',
                        poll_mode: tags[1] || \'N/A\',
                        voters: voters
                    });
                }
            });
            
            if (basicPollData.length > 0) {
                const workbook = XLSX.utils.book_new();
                const worksheet = XLSX.utils.json_to_sheet(basicPollData);
                
                worksheet[\'!cols\'] = [
                    { wch: 10 },
                    { wch: 35 },
                    { wch: 60 },
                    { wch: 15 },
                    { wch: 20 },
                    { wch: 15 }
                ];
                
                const range = XLSX.utils.decode_range(worksheet[\'!ref\']);
                for (let C = range.s.c; C <= range.e.c; ++C) {
                    const address = XLSX.utils.encode_cell({ r: 0, c: C });
                    if (worksheet[address]) {
                        worksheet[address].s = {
                            font: { bold: true, color: { rgb: "FFFFFF" } },
                            fill: { fgColor: { rgb: "0F6CBF" } },
                            alignment: { horizontal: "center" }
                        };
                    }
                }
                
                XLSX.utils.book_append_sheet(workbook, worksheet, \'Basic Poll Data\');
                
                let finalFilename;
                
                if (filename === \'all_polls\') {
                    finalFilename = \'All_Polls_Basic.xlsx\';
                } else if (filename === \'selected_polls\') {
                    finalFilename = \'Selected_Polls_Basic.xlsx\';
                } else if (filename === \'single_poll\') {
                    finalFilename = \'Single_Poll_Basic.xlsx\';
                } else {
                    // Fallback for unknown export types
                    finalFilename = \'Poll_Export_Basic.xlsx\';
                }
                
                XLSX.writeFile(workbook, finalFilename);
                
                showModal("Fallback Export Successful", `Exported ${basicPollData.length} polls with basic data to ${finalFilename}. Detailed export failed due to API permission issues. Check browser console for details.`, "warning");
            } else {
                showModal("Export Failed", "Could not export any poll data. Please check your connection and try again.", "error");
            }
        }
        
        function fetchDetailedPollData(pollId) {
            console.log(`Fetching detailed data for poll ${pollId}...`);
            return fetch("/blocks/poll/ajax_handler.php", {
                method: "POST",
                headers: {
                    \'Content-Type\': \'application/x-www-form-urlencoded\',
                },
                body: `action=get_poll_results&poll_id=${pollId}`
            })
            .then(response => {
                console.log(`Response status for poll ${pollId}:`, response.status);
                if (!response.ok) {
                    if (response.status === 403) {
                        throw new Error(\'Permission denied - insufficient access rights\');
                    } else if (response.status === 404) {
                        throw new Error(\'Poll not found\');
                    } else if (response.status === 500) {
                        throw new Error(\'Server error - check server logs\');
                    } else {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                }
                return response.json();
            })
            .then(data => {
                console.log(`Response data for poll ${pollId}:`, data);
                if (data.success) {
                    if (!data.poll || !data.options || !data.professors) {
                        throw new Error(\'Invalid poll data structure received\');
                    }
                    return data;
                } else {
                    throw new Error(data.message || \'Failed to fetch poll data\');
                }
            })
            .catch(error => {
                console.error(`Error fetching data for poll ${pollId}:`, error.message);
                throw error;
            });
        }
        
        function createPollWorksheet(pollData) {
            // Validate input data
            if (!pollData || !pollData.poll) {
                console.error(\'createPollWorksheet: Invalid pollData\');
                throw new Error(\'Invalid poll data provided\');
            }
            
            const poll = pollData.poll;
            const options = pollData.options || [];
            const votes = pollData.votes || [];
            const professors = pollData.professors || [];
            
            console.log(\'Creating worksheet for poll:\', poll.id, \'Options:\', options.length, \'Professors:\', professors.length, \'Votes:\', votes.length);
            
            const worksheetData = [];
            
            // 1. Poll Information (at the top)
            worksheetData.push([\'Poll Information\']);
            worksheetData.push([\'Title\', poll.title]);
            worksheetData.push([\'Description\', poll.description || \'N/A\']);
            worksheetData.push([\'Poll Type\', poll.poll_type || \'single\']);
            worksheetData.push([\'Poll Mode\', poll.poll_mode || \'text\']);
            worksheetData.push([\'Created\', new Date(poll.created_at * 1000).toLocaleString()]);
            worksheetData.push([]);
            
            // 2. Voting Statistics (summary)
            worksheetData.push([\'Voting Statistics\']);
            worksheetData.push([\'Total Professors\', professors.length]);
            worksheetData.push([\'Total Votes\', votes.length]);
            worksheetData.push([\'Participation Rate\', `${Math.round((votes.length / professors.length) * 100)}%`]);
            worksheetData.push([]);
            
            // 3. Option Statistics (summary)
            worksheetData.push([\'Option Statistics\']);
            options.forEach((option, index) => {
                const optionVotes = votes.filter(vote => vote.option_id === option.id).length;
                const percentage = professors.length > 0 ? Math.round((optionVotes / professors.length) * 100) : 0;
                worksheetData.push([option.option_text || `Option ${index + 1}`, optionVotes, `${percentage}%`]);
            });
            worksheetData.push([]);
            
            // 4. Voting Matrix (professors, options, and their votes) - AT THE END
            worksheetData.push([\'Voting Matrix\']);
            worksheetData.push([]);
            
            const headerRow = [\'Professor\', \'Email\'];
            options.forEach((option, index) => {
                headerRow.push(option.option_text || `Option ${index + 1}`);
            });
            worksheetData.push(headerRow);
            
            professors.forEach(professor => {
                const row = [professor.firstname + \' \' + professor.lastname, professor.email];
                
                const professorVotes = votes.filter(vote => vote.user_id === professor.id);
                
                options.forEach(option => {
                    const hasVoted = professorVotes.some(vote => vote.option_id === option.id);
                    row.push(hasVoted ? \'✓\' : \'\');
                });
                
                worksheetData.push(row);
            });
            
            worksheetData.push([]);
            worksheetData.push([\'Detailed Voting Breakdown\']);
            worksheetData.push([\'Professor\', \'Option Voted\']);
            
            votes.forEach(vote => {
                const professor = professors.find(p => p.id === vote.user_id);
                const option = options.find(o => o.id === vote.option_id);
                if (professor && option) {
                    worksheetData.push([
                        professor.firstname + \' \' + professor.lastname,
                        option.option_text || \'Unknown Option\'
                    ]);
                }
            });
            
            const worksheet = XLSX.utils.aoa_to_sheet(worksheetData);
            
            const columnWidths = [
                { wch: 25 },
                { wch: 30 },
            ];
            
            pollData.options.forEach(() => {
                columnWidths.push({ wch: 20 });
            });
            
            worksheet[\'!cols\'] = columnWidths;
            
            
            if (pollData.options && pollData.professors && pollData.votes) {
            stylePollWorksheet(worksheet, pollData);
            }
            
            return worksheet;
        }
        
        function stylePollWorksheet(worksheet, pollData) {
            // Validate input data
            if (!pollData || !pollData.options || !pollData.professors || !pollData.votes) {
                console.warn(\'stylePollWorksheet: Invalid pollData, skipping styling\');
                return;
            }
            
            const range = XLSX.utils.decode_range(worksheet[\'!ref\']);
            
            // Style poll information section
            for (let R = 0; R < 7; R++) {
                for (let C = 0; C <= range.e.c; C++) {
                    const address = XLSX.utils.encode_cell({ r: R, c: C });
                    if (worksheet[address]) {
                        if (R === 0) {
                            // Poll Information header
                            worksheet[address].s = {
                                font: { bold: true, size: 14, color: { rgb: "FFFFFF" } },
                                fill: { fgColor: { rgb: "0F6CBF" } },
                                alignment: { horizontal: "center" }
                            };
                        } else if (C === 0) {
                            // Labels (Title, Description, etc.)
                            worksheet[address].s = {
                                font: { bold: true, color: { rgb: "0F6CBF" } },
                                fill: { fgColor: { rgb: "E3F2FD" } }
                            };
                        }
                    }
                }
            }
            
        
            const statsStartRow = 8;
            const statsHeaderAddress = XLSX.utils.encode_cell({ r: statsStartRow, c: 0 });
            if (worksheet[statsHeaderAddress]) {
                worksheet[statsHeaderAddress].s = {
                    font: { bold: true, size: 12, color: { rgb: "FFFFFF" } },
                    fill: { fgColor: { rgb: "FFC107" } },
                    alignment: { horizontal: "center" }
                };
            }
            
            // Style option statistics section
            const optionStatsStartRow = statsStartRow + 4;
            const optionStatsHeaderAddress = XLSX.utils.encode_cell({ r: optionStatsStartRow, c: 0 });
            if (worksheet[optionStatsHeaderAddress]) {
                worksheet[optionStatsHeaderAddress].s = {
                    font: { bold: true, size: 12, color: { rgb: "FFFFFF" } },
                    fill: { fgColor: { rgb: "17A2B8" } },
                    alignment: { horizontal: "center" }
                };
            }
            
        
            const matrixStartRow = optionStatsStartRow + (pollData.options ? pollData.options.length : 0) + 3;
            const matrixHeaderRow = matrixStartRow + 1;
            
            // Style "Voting Matrix" header
            const matrixHeaderAddress = XLSX.utils.encode_cell({ r: matrixStartRow, c: 0 });
            if (worksheet[matrixHeaderAddress]) {
                worksheet[matrixHeaderAddress].s = {
                    font: { bold: true, size: 12, color: { rgb: "FFFFFF" } },
                    fill: { fgColor: { rgb: "28A745" } },
                    alignment: { horizontal: "center" }
                };
            }
            
            // Style matrix column headers
            for (let C = 0; C <= range.e.c; C++) {
                const address = XLSX.utils.encode_cell({ r: matrixHeaderRow, c: C });
                if (worksheet[address]) {
                    worksheet[address].s = {
                        font: { bold: true, color: { rgb: "FFFFFF" } },
                        fill: { fgColor: { rgb: "6C757D" } },
                        alignment: { horizontal: "center" }
                    };
                }
            }
            
            
            const breakdownStartRow = matrixStartRow + (pollData.professors ? pollData.professors.length : 0) + 3;
            const breakdownHeaderAddress = XLSX.utils.encode_cell({ r: breakdownStartRow, c: 0 });
            if (worksheet[breakdownHeaderAddress]) {
                worksheet[breakdownHeaderAddress].s = {
                    font: { bold: true, size: 12, color: { rgb: "FFFFFF" } },
                    fill: { fgColor: { rgb: "6F42C1" } },
                    alignment: { horizontal: "center" }
                };
            }
            
            
            for (let C = 0; C < 2; C++) {
                const address = XLSX.utils.encode_cell({ r: breakdownStartRow + 1, c: C });
                if (worksheet[address]) {
                    worksheet[address].s = {
                        font: { bold: true, color: { rgb: "FFFFFF" } },
                        fill: { fgColor: { rgb: "6C757D" } },
                        alignment: { horizontal: "center" }
                    };
                }
            }
        }
        
        function renderPollResults(polls) {
            const pollsList = document.getElementById(\'pollsList\');
            const pollsLoader = document.getElementById(\'pollsLoader\');
            
            if (!pollsList) {
                console.error("Polls list container not found");
                return;
            }
            
            if (pollsLoader) {
                pollsLoader.style.display = \'none\';
            }
            
            console.log("Rendering poll results. Polls to render:", polls.length);
            console.log("Polls data:", polls);
            
            if (polls.length === 0) {
                pollsList.innerHTML = \'<div class="no-polls"><p>No polls available yet</p></div>\';
                console.log("No polls to render, showing empty message");
                return;
            }
            
            let html = \'\';
            polls.forEach(poll => {
                console.log("Rendering poll:", poll.id, poll.title);
                console.log("Poll data received:", poll);
                const total_votes = poll.voted_count || 0;
                const poll_type = poll.poll_type || \'single\';
                const poll_mode = poll.poll_mode || \'text\';
                
                console.log("Raw poll_type:", poll_type, "Raw poll_mode:", poll_mode);
                
                
                const pollTypeDisplay = poll_type === \'multiple\' ? getTranslatedText(\'Multiple Choice\') : getTranslatedText(\'Single Choice\');
                
                
                let pollModeDisplay = getTranslatedText(\'Text\');
                if (poll_mode === \'timeslot\') {
                    pollModeDisplay = getTranslatedText(\'Time Slot\');
                } else if (poll_mode === \'custom_timeslot\') {
                    pollModeDisplay = getTranslatedText(\'Custom Time Slot\');
                } else if (poll_mode === \'text\') {
                    pollModeDisplay = getTranslatedText(\'Text\');
                }
                
                console.log("Display values - Type:", pollTypeDisplay, "Mode:", pollModeDisplay);
                
                html += \'<div class="poll-item" data-poll-id="\' + poll.id + \'" style="display: flex; align-items: center; gap: 10px; padding: 15px; border: 1px solid #e9ecef; border-radius: 8px; margin-bottom: 15px; background: white;">\';
                html += \'<div class="poll-checkbox" style="flex-shrink: 0;">\';
                html += \'<input type="checkbox" class="poll-select" onchange="updateSelectedCount()">\';
                html += \'</div>\';
                html += \'<div class="poll-content" style="flex: 1; margin: 0 15px; min-width: 0;">\';
                html += \'<div class="poll-title" style="font-size: 16px; font-weight: 600; color: #333; margin-bottom: 8px;">\' + poll.title + \'</div>\';
                html += \'<div class="poll-description" style="color: #666; font-size: 14px; margin-bottom: 12px;">Please select your preferred time slots for \' + poll.title + \'</div>\';
                html += \'<div class="poll-tags" style="display: flex; gap: 8px; flex-wrap: wrap;">\';
                html += \'<span class="poll-tag" style="background: #e9ecef; color: #495057; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 500;">\' + pollTypeDisplay + \'</span>\';
                html += \'<span class="poll-tag" style="background: #e9ecef; color: #495057; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 500;">\' + pollModeDisplay + \'</span>\';
                html += \'<span class="poll-tag voters" style="background: #28a745; color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 500;">\' + total_votes + \' \' + getTranslatedText(\'voters\') + \'</span>\';
                html += \'</div>\';
                html += \'</div>\';
                html += \'<div class="poll-actions-right" style="display: flex; gap: 8px; align-items: center; flex-shrink: 0; margin-left: auto;">\';
                html += \'<button class="action-btn-small export-poll" onclick="exportSinglePoll(\' + poll.id + \')" title="Export Poll" style="background: #28a745; color: white; border: none; padding: 8px 12px; border-radius: 4px; cursor: pointer; font-size: 12px; min-width: 80px; display: flex; align-items: center; gap: 4px;">\';
                html += \'<span class="btn-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg></span>\';
                html += \'Export\';
                html += \'</button>\';
                html += \'<button class="action-btn-small view-results" onclick="viewPollResults(\' + poll.id + \')" title="View Results" style="background: #17a2b8; color: white; border: none; padding: 8px 12px; border-radius: 4px; cursor: pointer; font-size: 12px;">\';
                html += \'<span class="btn-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/></svg></span>\';
                html += \'</button>\';
                html += \'<button class="action-btn-small delete-poll" onclick="deletePoll(\' + poll.id + \')" title="Delete Poll" style="background: #dc3545; color: white; border: none; padding: 8px 12px; border-radius: 4px; cursor: pointer; font-size: 12px;">\';
                html += \'<span class="btn-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg></span>\';
                html += \'</button>\';
                html += \'</div>\';
                html += \'</div>\';
            });
            
            pollsList.innerHTML = html;
            console.log("Poll results rendered successfully. HTML length:", html.length);
        }
        
        function loadPollStatistics() {
            console.log("Loading poll statistics...");
            const formData = new FormData();
            formData.append("action", "get_poll_statistics");
            if (window.M && M.cfg && M.cfg.sesskey) {
                formData.append(\'sesskey\', M.cfg.sesskey);
            }
            
            fetch("/blocks/poll/ajax_handler.php", {
                method: "POST",
                body: formData
            })
            .then(response => {
                console.log("Response status:", response.status);
                return response.json();
            })
            .then(data => {
                console.log("Response data:", data);
                if (data.success) {
                    console.log("Poll statistics loaded successfully. Polls found:", data.polls.length);
                    renderPollStatistics(data);
                } else {
                    console.error("Error loading poll statistics:", data.message);
                    showModal("Error", "Error loading poll statistics: " + (data.message || "Unknown error"), "error");
                }
            })
            .catch(error => {
                console.error("Poll statistics error:", error);
                showModal("Error", "Network error occurred while loading poll statistics.", "error");
            });
        }
        
        function renderPollStatistics(data) {
            const loader = document.getElementById(\'nonVotingLoader\');
            const grid = document.getElementById(\'nonVotingGrid\');
            const stats = document.getElementById(\'overallStatistics\');
            
            loader.style.display = \'none\';
            
            renderPollCards(data.polls, grid);
            
            renderOverallStatistics(data.overall_stats, stats);
            
            grid.style.display = \'block\';
            stats.style.display = \'block\';
        }
        
        function renderPollCards(polls, container) {
            console.log("Rendering poll cards. Polls to render:", polls.length);
            console.log("Polls data:", polls);
            
            if (polls.length === 0) {
                container.innerHTML = \'<div class="no-polls">No polls available yet</div>\';
                console.log("No polls to render, showing empty message");
                return;
            }
            
            let html = \'\';
            polls.forEach(poll => {
                console.log("Rendering poll:", poll.id, poll.title);
                html += \'<div class="poll-status-card" onclick="viewProfessorDetails(\' + poll.id + \')">\';
                html += \'<div class="card-title">\' + poll.title + \'</div>\';
                html += \'<div class="voting-status">\';
                html += \'<span class="voted-count">\' + poll.voted_count + \' \' + getTranslatedText(\'voted\') + \'</span>\';
                html += \'<span class="not-voted-count">\' + poll.not_voted_count + \' \' + getTranslatedText(\'not voted\') + \'</span>\';
                html += \'</div>\';
                html += \'<div class="participation-rate">\' + poll.participation_rate + \'% \' + getTranslatedText(\'participation rate\') + \' • \' + getTranslatedText(\'Click to see details\') + \'</div>\';
                html += \'</div>\';
            });
            
            container.innerHTML = html;
            console.log("Poll cards rendered successfully. HTML length:", html.length);
        }
        
        function renderOverallStatistics(stats, container) {
            const summary = document.getElementById(\'statsSummary\');
            const chart = document.getElementById(\'barChart\');
            const counts = document.getElementById(\'totalCounts\');
            
            summary.textContent = \'\';
            
            counts.textContent = stats.total_polls + \' \' + getTranslatedText(\'polls\') + \' • \' + stats.total_votes + \' \' + getTranslatedText(\'total votes\') + \' • \' + stats.total_not_voted + \' \' + getTranslatedText(\'total not voted\');
            
            const totalProfessors = stats.total_votes + stats.total_not_voted;
            const votedPercentage = totalProfessors > 0 ? Math.round((stats.total_votes / totalProfessors) * 100) : 0;
            const notVotedPercentage = totalProfessors > 0 ? Math.round((stats.total_not_voted / totalProfessors) * 100) : 0;
            
            const maxHeight = 180;
            const votedHeight = Math.max(votedPercentage * 1.8, 20);
            const notVotedHeight = Math.max(notVotedPercentage * 1.8, 20);
            
            chart.innerHTML = \'<div class="overall-chart-bar voted-bar domino-bar" style="height: \' + votedHeight + \'px; background: linear-gradient(180deg, #28a745 0%, #20c997 100%); z-index: 2;">\' +
                \'<div class="bar-label">\' + getTranslatedText(\'Voted\') + \'<br>(\' + votedPercentage + \'%)</div>\' +
                \'</div>\' +
                \'<div class="overall-chart-bar not-voted-bar domino-bar" style="height: \' + notVotedHeight + \'px; background: linear-gradient(180deg, #dc3545 0%, #c82333 100%); z-index: 1; margin-left: -30px;">\' +
                \'<div class="bar-label">\' + getTranslatedText(\'Not Voted\') + \'<br>(\' + notVotedPercentage + \'%)</div>\' +
                \'</div>\';
        }
        
        function toggleNonVotingSection() {
            const content = document.getElementById(\'nonVotingContent\');
            const btn = document.querySelector(\'.collapse-btn\');
            
            if (content.style.display === \'none\') {
                content.style.display = \'block\';
                btn.textContent = \'Collapse\';
            } else {
                content.style.display = \'none\';
                btn.textContent = \'Expand\';
            }
        }
        
        window.testPollResultsAPI = function(pollId) {
            console.log(`Testing poll results API for poll ${pollId}...`);
            
            const formData = new FormData();
            formData.append("action", "get_poll_results");
            formData.append("poll_id", pollId);
            if (window.M && M.cfg && M.cfg.sesskey) {
                formData.append(\'sesskey\', M.cfg.sesskey);
            }
            
            fetch("/blocks/poll/ajax_handler.php", {
                method: "POST",
                body: formData
            })
            .then(response => {
                console.log(`API Response Status: ${response.status}`);
                return response.json();
            })
            .then(data => {
                console.log(`API Response Data:`, data);
                if (data.success) {
                    console.log(` API Success - Poll: ${data.poll.title}, Options: ${data.options.length}, Professors: ${data.professors.length}, Votes: ${data.votes.length}`);
                } else {
                    console.log(` API Error: ${data.message}`);
                }
            })
            .catch(error => {
                console.error(` API Network Error:`, error);
            });
        };

        function viewProfessorDetails(pollId) {
            showModal(\'Loading\', \'Loading professor details...\', \'info\');
            
            const formData = new FormData();
            formData.append("action", "get_professor_details");
            formData.append("poll_id", pollId);
            if (window.M && M.cfg && M.cfg.sesskey) {
                formData.append(\'sesskey\', M.cfg.sesskey);
            }
            
            fetch(\'/blocks/poll/ajax_handler.php\', {
                method: "POST",
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showProfessorDetailsModal(data);
                } else {
                    showModal(\'Error\', \'Error loading professor details: \' + (data.message || \'Unknown error\'), \'error\');
                }
            })
            .catch(error => {
                console.error(\'Professor details error:\', error);
                showModal(\'Error\', \'Network error occurred while loading professor details.\', \'error\');
            });
        }
        
        function showProfessorDetailsModal(data) {
            const modal = document.getElementById(\'pollModal\');
            const modalTitle = document.getElementById(\'modalTitle\');
            const modalMessage = document.getElementById(\'modalMessage\');
            const modalIcon = document.getElementById(\'modalIcon\');
            const modalBtn = document.getElementById(\'modalBtn\');
            
            modalTitle.textContent = data.poll.title + \' - \' + getTranslatedText(\'Professor Details\');
            
            let message = \'<div class="professor-details">\';
            message += \'<div class="voting-summary">\';
            message += \'<p><strong>\' + getTranslatedText(\'Voting Summary:\') + \'</strong></p>\';
            message += \'<p>✓ \' + data.voted_count + \' \' + getTranslatedText(\'voted\') + \'</p>\';
            message += \'<p>✗ \' + data.not_voted_count + \' \' + getTranslatedText(\'not voted\') + \'</p>\';
            message += \'<p><strong>\' + data.participation_rate + \'% \' + getTranslatedText(\'participation rate\') + \'</strong></p>\';
            message += \'</div>\';
            
            if (data.voted_professors.length > 0) {
                message += \'<div class="voted-professors">\';
                message += \'<p><strong>\' + getTranslatedText(\'Professors who voted\') + \' (\' + data.voted_professors.length + \'):</strong></p>\';
                message += \'<div class="professor-list">\';
                data.voted_professors.forEach(prof => {
                    message += \'<div class="professor-item voted">\';
                    message += \'<span class="prof-name">\' + prof.firstname + \' \' + prof.lastname + \'</span>\';
                    message += \'<span class="prof-email">\' + prof.email + \'</span>\';
                    message += \'</div>\';
                });
                message += \'</div></div>\';
            }
            
            if (data.not_voted_professors.length > 0) {
                message += \'<div class="voted-professors">\';
                message += \'<p><strong>\' + getTranslatedText(\'Professors who did not vote\') + \' (\' + data.not_voted_professors.length + \'):</strong></p>\';
                message += \'<div class="professor-list">\';
                data.not_voted_professors.forEach(prof => {
                    message += \'<div class="professor-item not-voted">\';
                    message += \'<span class="prof-name">\' + prof.firstname + \' \' + prof.lastname + \'</span>\';
                    message += \'<span class="prof-email">\' + prof.email + \'</span>\';
                    message += \'</div>\';
                });
                message += \'</div></div>\';
            } else {
                message += \'<div class="all-voted">\';
                message += \'<p><strong>\' + getTranslatedText(\'All professors voted!\') + \'</strong></p>\';
                message += \'</div>\';
            }
            
            message += \'</div>\';
            
            modalMessage.innerHTML = message;
            modalIcon.innerHTML = \'<svg width="48" height="48" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>\';
            modalIcon.className = \'modal-icon info\';
            modalBtn.textContent = getTranslatedText(\'Close\');
            modalBtn.onclick = closeModal;
            
            modal.classList.add(\'show\');
        }
        
    
        function confirmAndSubmitVote(pollId) {
            const pollContainer = document.querySelector(\'[data-poll-id="\' + pollId + \'"]\');
            
            if (!pollContainer) {
                
                const pollItem = document.getElementById(\'poll_item_\' + pollId);
                if (!pollItem) {
                    alert(\'Poll container not found\');
                    return;
                }
                pollContainer = pollItem;
            }
            
            // Get the selected options
            const selectedOptions = pollContainer.querySelectorAll(\'input[name="option_id_\' + pollId + \'"]:checked, input[name="option_id_\' + pollId + \'[]"]:checked\');
            if (selectedOptions.length === 0) {
                alert(\'Please select at least one option before voting\');
                return;
            }
            
            // Get the selected option text(s)
            const selectedTexts = Array.from(selectedOptions).map(option => {
                const label = option.nextElementSibling;
                return label ? label.textContent.trim() : \'Unknown option\';
            });
            
            showVoteConfirmationModal(pollId, selectedOptions, selectedTexts);
        }
        
        function showVoteConfirmationModal(pollId, selectedOptions, selectedTexts) {
            const modal = document.getElementById(\'pollModal\');
            const modalTitle = document.getElementById(\'modalTitle\');
            const modalMessage = document.getElementById(\'modalMessage\');
            const modalIcon = document.getElementById(\'modalIcon\');
            const modalBtn = document.getElementById(\'modalBtn\');
            const modalCancelBtn = document.getElementById(\'modalCancelBtn\');
            
            modalTitle.textContent = getTranslatedText(\'PERMANENT VOTE CONFIRMATION\');
            
            
            let messageHTML = \'<div style="text-align: left; margin-bottom: 20px;">\';
            messageHTML += \'<p style="margin: 0 0 15px 0; font-weight: 600; color: #dc3545;">⚠️ \' + getTranslatedText(\'This vote cannot be changed once submitted!\') + \'</p>\';
            messageHTML += \'<p style="margin: 0 0 15px 0;">\' + getTranslatedText(\'You are about to vote for:\') + \'</p>\';
            
            
            selectedTexts.forEach(text => {
                messageHTML += \'<div class="vote-confirmation-option" style="background: #f8f9fa; padding: 12px 16px; margin: 8px 0; border-radius: 6px; border-left: 4px solid #007bff; text-align: left; font-weight: 500; color: #333;">\' + text + \'</div>\';
            });
            
            messageHTML += \'<p style="margin: 15px 0 0 0; font-weight: 600; color: #495057;">\' + getTranslatedText(\'Continue?\') + \'</p>\';
            messageHTML += \'</div>\';
            
            modalMessage.innerHTML = messageHTML;
            modalIcon.innerHTML = \'<svg width="48" height="48" viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>\';
            modalIcon.className = \'modal-icon success\';
            
            modalBtn.textContent = getTranslatedText(\'Submit Vote\');
            modalBtn.className = \'modal-btn\';
            modalBtn.onclick = function() {
                closeModal();
                submitVote(pollId, selectedOptions);
            };
            
            modalCancelBtn.textContent = getTranslatedText(\'Cancel\');
            modalCancelBtn.style.display = \'inline-block\';
            modalCancelBtn.onclick = closeModal;
            
            modal.classList.add(\'show\');
        }
        
        function submitVote(pollId, selectedOptions) {
            const formData = new FormData();
            
            
            const pollContainer = document.querySelector(\'[data-poll-id="\' + pollId + \'"]\');
            const isMultiple = pollContainer && pollContainer.getAttribute(\'data-poll-type\') === \'multiple\';
            
            if (isMultiple) {
                formData.append(\'action\', \'submit_multiple_choice_vote\');
                formData.append(\'poll_id\', pollId);
                
                selectedOptions.forEach((option, index) => {
                    formData.append(\'option_ids[]\', option.value);
                });
            } else {
                formData.append(\'action\', \'submit_vote\');
                formData.append(\'poll_id\', pollId);
                
                formData.append(\'option_id\', selectedOptions[0].value);
            }
            if (window.M && M.cfg && M.cfg.sesskey) {
                formData.append(\'sesskey\', M.cfg.sesskey);
            }
            
            // Show loading state
            showModal(\'Submitting Vote\', \'Please wait while we process your vote...\', \'info\');
            
            fetch(\'/blocks/poll/ajax_handler.php\', {
                method: \'POST\',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                closeModal();
                if (data.success) {
                    showModal(\'Vote Submitted\', \'Your vote has been recorded successfully!\', \'success\', true);
                    // Refresh the page after a delay
                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                } else {
                    showModal(\'Vote Failed\', \'Error submitting vote: \' + (data.message || \'Unknown error\'), \'error\');
                }
            })
            .catch(error => {
                closeModal();
                console.error(\'Vote submission error:\', error);
                showModal(\'Vote Failed\', \'Network error occurred while submitting vote.\', \'error\');
            });
        }
        </script>';
        
        return $content;
    }

    private function get_professor_content() {
        global $DB, $USER;
        
        $content = '';
        
        $polls = $DB->get_records('block_poll_polls', array('active' => 1), 'time_created DESC');
        
        $content .= '<div style="padding: 16px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 6px;">
            <h3 style="margin: 0 0 16px 0; color: #333; font-size: 16px; font-weight: 600; border-bottom: 1px solid #dee2e6; padding-bottom: 8px;" data-translate="Available Polls">Available Polls</h3>
            <div class="available-polls-wrapper" style="background: white; border: 1px solid #dee2e6; border-radius: 4px; overflow: hidden;">';
        
        if ($polls) {
            foreach ($polls as $poll) {
                $options = $DB->get_records('block_poll_options', array('poll_id' => $poll->id), 'sort_order');
                $user_vote = $DB->get_record('block_poll_votes', array('poll_id' => $poll->id, 'user_id' => $USER->id));
                $poll_type = $poll->poll_type ?? 'single';
                
                // Determine if poll has been voted on and set appropriate styling
                $has_voted = !empty($user_vote);
                $header_bg = $has_voted ? '#f8f9fa' : '#fff3cd'; // Light yellow for unpolled
                $header_border = $has_voted ? '#dee2e6' : '#ffeaa7'; // Yellow border for unpolled
                $hover_bg = $has_voted ? '#e9ecef' : '#ffeaa7'; // Darker yellow on hover for unpolled
                
                $content .= '<div class="poll_voting_item" style="border-bottom: 1px solid ' . $header_border . '; background: white; overflow: hidden;" id="poll_item_' . $poll->id . '">
                    <!-- Poll Header (Always Visible) -->
                    <div class="poll_header" style="padding: 12px; cursor: pointer; background: ' . $header_bg . '; border-bottom: 1px solid ' . $header_border . ';" onclick="togglePollContent(' . $poll->id . ')" onmouseover="this.style.background=\'' . $hover_bg . '\'" onmouseout="this.style.background=\'' . $header_bg . '\'">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div style="flex: 1;">
                                <h4 class="poll_title" style="margin: 0 0 6px 0; color: #333; font-size: 14px; font-weight: 600;">
                                    ' . htmlspecialchars($poll->title) . '
                                </h4>';
                                
                                // Add voting status indicator
                                if ($has_voted) {
                                    $content .= '<div style="display: inline-block; background: #28a745; color: white; padding: 2px 8px; border-radius: 12px; font-size: 10px; font-weight: bold; margin-bottom: 6px;">
                                        <span data-translate="voted">✓ VOTED</span>
                                    </div>';
                                } else {
                                    $content .= '<div style="display: inline-block; background: #ffc107; color: #212529; padding: 2px 8px; border-radius: 12px; font-size: 10px; font-weight: bold; margin-bottom: 6px;">
                                        <span data-translate="not_voted">⚠ NOT VOTED</span>
                                    </div>';
                                }
                
                $description = isset($poll->description) ? trim($poll->description) : '';
                if (!empty($description)) {
                    $content .= '                                    <div class="poll_description" style="color: #666; font-size: 12px; line-height: 1.3; margin: 4px 0;">
                                        <strong data-translate="Description">Description:</strong> ' . htmlspecialchars($description) . '
                                  </div>';
                } else {
                    $content .= '<div class="poll_description" style="color: #999; font-size: 11px; line-height: 1.3; margin: 4px 0; font-style: italic;">
                                    No description available
                                  </div>';
                }
                
                $mode_display = '';
                switch($poll->poll_mode) {
                    case 'text':
                        $mode_display = get_string('text_poll_type', 'block_poll');
                        break;
                    case 'time':
                        $mode_display = get_string('time_slots', 'block_poll');
                        break;
                    case 'custom_timeslot':
                        $mode_display = get_string('custom_defense_slots', 'block_poll');
                        break;
                    default:
                        $mode_display = get_string('poll', 'block_poll');
                }
                
                $content .= '<div class="poll_info_badges" style="display: flex; gap: 8px; margin-top: 6px; flex-wrap: wrap;">
                                    <span style="display: inline-block; background: #e9ecef; color: #495057; padding: 2px 6px; border-radius: 3px; font-size: 11px;">
                                        <span data-translate="' . ($poll->poll_mode === 'text' ? 'text_poll_type' : ($poll->poll_mode === 'time' ? 'time_slots' : ($poll->poll_mode === 'custom_timeslot' ? 'custom_defense_slots' : 'poll'))) . '">' . $mode_display . '</span>
                                    </span>
                                    <span style="display: inline-block; background: #e9ecef; color: #495057; padding: 2px 6px; border-radius: 3px; font-size: 11px;">
                                        ' . count($options) . ' <span data-translate="options_count_label">' . get_string('options_count_label', 'block_poll') . '</span>
                                    </span>
                                    <span style="display: inline-block; background: ' . ($poll_type === 'multiple' ? '#28a745' : '#6c757d') . '; color: white; padding: 2px 6px; border-radius: 3px; font-size: 11px; font-weight: bold;">
                                        <span data-translate="' . ($poll_type === 'multiple' ? 'multiple_choice' : 'single_choice') . '">' . ($poll_type === 'multiple' ? get_string('multiple_choice', 'block_poll') : get_string('single_choice', 'block_poll')) . '</span>
                                    </span>
                                    <span style="display: inline-block; background: #e9ecef; color: #495057; padding: 2px 6px; border-radius: 3px; font-size: 11px;">
                                        <span data-translate="created_on_label">' . get_string('created_on_label', 'block_poll') . '</span>: ' . date('M j', $poll->time_created) . '
                                    </span>
                                </div>';
                
                $content .= '</div>
                            <div class="poll_toggle_icon" style="font-size: 12px; color: #666; font-weight: bold;" id="toggle_icon_' . $poll->id . '">
                                ▼
                            </div>
                        </div>
                    </div>
                    
                    <!-- Poll Content (Collapsible) -->
                    <div class="poll_content" id="poll_content_' . $poll->id . '" style="display: none; padding: 12px; background: white; border-top: 1px solid #dee2e6;">';
                
                if ($user_vote) {
                    if ($poll_type === 'multiple') {
                        $voted_options = $DB->get_records('block_poll_votes', array('poll_id' => $poll->id, 'user_id' => $USER->id));
                        $voted_option_texts = array();
                        
                        foreach ($voted_options as $vote) {
                            $option = $DB->get_record('block_poll_options', array('id' => $vote->option_id));
                            if ($option) {
                                $voted_option_texts[] = htmlspecialchars($option->option_text);
                            }
                        }
                        
                        $voted_text = implode(', ', $voted_option_texts);
                        $vote_label = count($voted_options) > 1 ? get_string('your_votes', 'block_poll') : get_string('your_vote', 'block_poll');
                        $vote_count = count($voted_options);
                        
                        $content .= '<div class="poll_voted" style="background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; padding: 10px; margin: 8px 0;">';
                        $content .= '<div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">';
                        $content .= '<span style="background: #28a745; color: white; padding: 4px 8px; border-radius: 3px; font-size: 12px; font-weight: bold;"><span data-translate="voted_label">' . get_string('voted_label', 'block_poll') . '</span></span>';
                        $content .= '<span style="color: #721c24; font-weight: bold; font-size: 11px;"><span data-translate="permanent_vote">' . get_string('permanent_vote', 'block_poll') . '</span></span>';
                        
                        if ($vote_count > 1) {
                            $content .= '<span style="background: #17a2b8; color: white; padding: 4px 8px; border-radius: 3px; font-size: 11px; font-weight: bold;">' . $vote_count . ' <span data-translate="options_selected">' . get_string('options_selected', 'block_poll') . '</span></span>';
                        }
                        
            $content .= '</div>';
                        
                                                if ($vote_count > 1) {
                            $content .= '<div style="margin: 8px 0;">';
                            $content .= '<p style="margin: 0; font-size: 13px;"><strong><span data-translate="selected_options">' . get_string('selected_options', 'block_poll') . '</span>:</strong></p>';
                            $content .= '<div style="margin: 8px 0; padding: 8px; background: #f8f9fa; border-radius: 3px; border-left: 3px solid #17a2b8;">';
                            $content .= '<ul style="margin: 0; padding-left: 20px; color: #495057; font-size: 12px;">';
                            
                            foreach ($voted_option_texts as $text) {
                                $formatted_text = $text;
                                if (strpos($text, ' - ') !== false) {
                                    $parts = explode(' - ', $text);
                                    if (count($parts) == 2) {
                                        $start = trim($parts[0]);
                                        $end = trim($parts[1]);
                                        
                                        $start_formatted = preg_replace('/(\d{1,2}:\d{2}\s*[AP]M)/', ' <strong style="color: #007bff;">$1</strong>', $start);
                                        $end_formatted = preg_replace('/(\d{1,2}:\d{2}\s*[AP]M)/', ' <strong style="color: #007bff;">$1</strong>', $end);
                                        
                                        $formatted_text = $start_formatted . ' <span style="color: #6c757d; font-weight: bold;">-</span> ' . $end_formatted;
                                    }
                                }
                                
                                $content .= '<li style="margin: 8px 0; padding: 6px 8px; background: #ffffff; border-radius: 3px; border-left: 3px solid #17a2b8; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">' . $formatted_text . '</li>';
                            }
                            
                            $content .= '</ul>';
            $content .= '</div>';
                            $content .= '</div>';
                        } else {
                            $content .= '<p style="margin: 0; font-size: 13px;"><strong><span data-translate="your_vote">' . get_string('your_vote', 'block_poll') . '</span>:</strong> ' . $voted_text . '</p>';
        }

                        $content .= '<p style="margin: 4px 0 0 0; color: #721c24; font-size: 11px; font-style: italic;"><span data-translate="vote_cannot_be_changed">' . get_string('vote_cannot_be_changed', 'block_poll') . '</span></p>';
        $content .= '</div>';
                    } else {
                        $voted_option = $DB->get_record('block_poll_options', array('id' => $user_vote->option_id));
                        $content .= '<div class="poll_voted" style="background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; padding: 10px; margin: 8px 0;">
                            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                                <span style="background: #28a745; color: white; padding: 4px 8px; border-radius: 3px; font-size: 12px; font-weight: bold;"><span data-translate="voted_label">' . get_string('voted_label', 'block_poll') . '</span></span>
                                <span style="background: #721c24; color: white; padding: 4px 8px; border-radius: 3px; font-size: 12px; font-weight: bold;"><span data-translate="permanent_vote">' . get_string('permanent_vote', 'block_poll') . '</span></span>
                            </div>
                            <p style="margin: 0; font-size: 13px;"><strong><span data-translate="your_vote">' . get_string('your_vote', 'block_poll') . '</span>:</strong> ' . htmlspecialchars($voted_option->option_text) . '</p>
                            <p style="margin: 4px 0 0 0; color: #721c24; font-size: 11px; font-style: italic;"><span data-translate="vote_cannot_be_changed">' . get_string('vote_cannot_be_changed', 'block_poll') . '</span></p>
                        </div>';
                    }
                } else {
                    $content .= '<div class="poll_voting_form" data-poll-id="' . $poll->id . '" data-poll-type="' . ($poll_type ?: 'single') . '">';
                    
                    $content .= '<div class="poll_options" style="margin: 15px 0;">
                        <h5 style="margin: 0 0 15px 0; color: #333; font-size: 13px; font-weight: bold; padding: 8px 0; border-bottom: 1px solid #dee2e6;">
                            <span data-translate="' . ($poll_type === 'multiple' ? 'select_your_vote_multiple' : 'select_your_vote_single') . '">' . ($poll_type === 'multiple' ? get_string('select_your_vote_multiple', 'block_poll') : get_string('select_your_vote_single', 'block_poll')) . '</span>
                        </h5>';
                    
                    foreach ($options as $option) {
                        $input_type = ($poll_type === 'multiple') ? 'checkbox' : 'radio';
                        $input_name = ($poll_type === 'multiple') ? 'option_id_' . $poll->id . '[]' : 'option_id_' . $poll->id;
                        
                        $content .= '<div class="form-check" style="margin: 8px 0; padding: 8px 12px; border: 1px solid #dee2e6; border-radius: 4px; background: #f8f9fa; cursor: pointer;" onmouseover="this.style.background=\'#e9ecef\'" onmouseout="this.style.background=\'#f8f9fa\'">
                            <input class="form-check-input" type="' . $input_type . '" name="' . $input_name . '" id="option_' . $option->id . '" value="' . $option->id . '" required style="margin-right: 8px;">
                            <label class="form-check-label" for="option_' . $option->id . '" style="margin: 0; cursor: pointer; font-weight: 500; color: #333; width: 100%; display: block; font-size: 12px;">';
                        $content .= htmlspecialchars($option->option_text);
                        $content .= '</label>
                        </div>';
                    }
                    
                    $content .= '</div>';
                    
                    $content .= '<div class="vote_submit_section" style="text-align: center; margin: 15px 0;">'
                        . '<button type="button" class="btn btn-primary" onclick="confirmAndSubmitVote(' . $poll->id . ')" style="background: #007bff; border: 1px solid #0056b3; padding: 8px 20px; font-size: 12px; font-weight: 600; border-radius: 4px; color: white; cursor: pointer;">'
                        . '<span data-translate="submit_your_vote">' . get_string('submit_your_vote', 'block_poll') . '</span>'
                        . '</button>'
                    . '</div>';
                }
                
                $content .= '</div>
                </div>';
            }
        } else {
            $content .= '<p style="margin: 0; color: #666; font-size: 12px; font-style: italic; text-align: center; padding: 15px;">No polls available for voting</p>';
        }

        $content .= '</div>';



        return $content;
    }

    public function applicable_formats() {
        return array('site' => true, 'course' => true, 'my' => true);
    }

    public function instance_allow_multiple() {
        return false;
    }

    /**
     * Handle poll creation via AJAX
     */
    private function handle_create_poll($DB, $USER) {
        global $CFG;
        
        error_reporting(0);
        ini_set('display_errors', 0);
        
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        ob_start();
        
        try {
            error_log('Poll creation attempt - POST data: ' . print_r($_POST, true));
            
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
                    $this->handle_text_poll_options($DB, $poll_id, $_POST);
                    break;
                    
                case 'time':
                    $this->handle_time_poll_options($DB, $poll_id, $_POST);
                    break;
                    
                case 'custom_timeslot':
                    $this->handle_custom_timeslot_options($DB, $poll_id, $_POST);
                    break;
            }
            
            header('Content-Type: application/json');
            header('Cache-Control: no-cache, must-revalidate');
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: POST');
            header('Access-Control-Allow-Headers: Content-Type');
            
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
        
        exit;
    }
    
    /**
     * Handle text poll options
     */
    private function handle_text_poll_options($DB, $poll_id, $post_data) {
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
    private function handle_time_poll_options($DB, $poll_id, $post_data) {
        if (isset($post_data['timeslots']) && is_array($post_data['timeslots'])) {
            foreach ($post_data['timeslots'] as $index => $timeslot) {
                if (!empty($timeslot['start_time']) && !empty($timeslot['end_time'])) {
                    $option = new stdClass();
                    $option->poll_id = $poll_id;
                    $option->option_text = 'Time slot ' . ($index + 1);
                    $option->start_time = (int)$timeslot['start_time'];
                    $option->end_time = (int)$timeslot['end_time'];
                    $option->sort_order = $index;
                    $DB->insert_record('block_poll_options', $option);
                }
            }
        }
    }
    
    /**
     * Handle custom timeslot options
     */
    private function handle_custom_timeslot_options($DB, $poll_id, $post_data) {
        if (isset($post_data['timeslots']) && is_array($post_data['timeslots'])) {
            foreach ($post_data['timeslots'] as $index => $timeslot) {
                if (!empty($timeslot['start_time']) && !empty($timeslot['end_time'])) {
                    $option = new stdClass();
                    $option->poll_id = $poll_id;
                    $option->option_text = 'Custom timeslot ' . ($index + 1);
                    $option->start_time = (int)$timeslot['start_time'];
                    $option->end_time = (int)$timeslot['end_time'];
                    $option->sort_order = $index;
                    $DB->insert_record('block_poll_options', $option);
                }
            }
        }
    }
}
