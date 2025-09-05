

define(['jquery'], function($) {
    'use strict';

    return {
        init: function() {
            this.initPollForm();
            this.initDateTimePickers();
        },

        initPollForm: function() {

            this.updatePollType();
            this.updatePollMode();

            $('#pollType').on('change', this.updatePollType.bind(this));
            $('#pollMode').on('change', this.updatePollMode.bind(this));
            $('#ptManualEnd').on('change', this.toggleManualEndTime.bind(this));

            this.addPollOption();
            this.addPollOption();
        },

        updatePollType: function() {
            const pollType = $('#pollType').val();
            const securityNote = $('#pollSecurityNote');

            if (pollType === 'multiple') {
                securityNote.text('Multiple votes per user allowed');
                securityNote.addClass('multiple-choice');
            } else {
                securityNote.text('One vote per user');
                securityNote.removeClass('multiple-choice');
            }
        },

        updatePollMode: function() {
            const pollMode = $('#pollMode').val();

            $('#pollAnswerText, #pollAnswerTime, #pollAnswerCustomTimeslot').hide();

            switch(pollMode) {
                case 'text':
                    $('#pollAnswerText').show();
                    break;
                case 'time':
                    $('#pollAnswerTime').show();
                    break;
                case 'custom_timeslot':
                    $('#pollAnswerCustomTimeslot').show();
                    break;
            }
        },

        toggleManualEndTime: function() {
            const isManual = $('#ptManualEnd').is(':checked');
            const endTimeInput = $('#ptEnd');
            const endTimeButton = $('.calbtn[data-for="ptEnd"]');

            if (isManual) {
                endTimeInput.prop('disabled', false);
                endTimeButton.prop('disabled', false);
            } else {
                endTimeInput.prop('disabled', true);
                endTimeButton.prop('disabled', true);
            }
        },

        addPollOption: function() {
            const optionsBox = $('#pollOptionsBox');
            const optionCount = optionsBox.children().length + 1;

            const newOption = $(`
                <div class="poll-opt">
                    <input class="inp poll-opt-input" placeholder="Option ${optionCount}">
                    <button class="mini danger" onclick="removePollOption(this)">×</button>
                </div>
            `);

            optionsBox.append(newOption);
        },

        removePollOption: function(button) {
            const optionsBox = $('#pollOptionsBox');
            const options = optionsBox.children();

            if (options.length > 2) {
                $(button).closest('.poll-opt').remove();
                this.renumberOptions();
            } else {
                alert('Minimum 2 options required');
            }
        },

        renumberOptions: function() {
            const options = $('#pollOptionsBox .poll-opt');
            options.each(function(index) {
                $(this).find('.poll-opt-input').attr('placeholder', `Option ${index + 1}`);
            });
        },

        addCustomTimeslot: function() {
            const timeslotsContainer = $('#customTimeslots');
            const timeslotCount = timeslotsContainer.children().length + 1;

            const newTimeslot = $(`
                <div class="timeslot-option" data-timeslot="${timeslotCount}">
                    <div class="timeslot-grid">
                        <div class="timeslot-input">
                            <label class="req">Start date & time</label>
                            <input class="inp custom-start" type="text" placeholder="YYYY-MM-DDTHH:MM">
                        </div>
                        <div class="timeslot-input">
                            <label class="req">End date & time</label>
                            <input class="inp custom-end" type="text" placeholder="YYYY-MM-DDTHH:MM">
                        </div>
                    </div>
                    <button class="remove-timeslot" onclick="removeCustomTimeslot(this)">×</button>
                </div>
            `);

            timeslotsContainer.append(newTimeslot);
            this.initDateTimePickers();
        },

        removeCustomTimeslot: function(button) {
            const timeslotsContainer = $('#customTimeslots');
            const timeslots = timeslotsContainer.children();

            if (timeslots.length > 1) {
                $(button).closest('.timeslot-option').remove();
                this.renumberTimeslots();
            } else {
                alert('Minimum 1 timeslot required');
            }
        },

        renumberTimeslots: function() {
            const timeslots = $('#customTimeslots .timeslot-option');
            timeslots.each(function(index) {
                $(this).attr('data-timeslot', index + 1);
            });
        },

        initDateTimePickers: function() {

            $('.dtp-input, .custom-start, .custom-end').each(function() {
                this.addEventListener('focus', function() {
                    this.type = 'datetime-local';
                });

                this.addEventListener('blur', function() {
                    if (!this.value) {
                        this.type = 'text';
                    }
                });
            });
        },

        validateForm: function() {
            const title = $('#pollTitle').val().trim();
            const pollMode = $('#pollMode').val();

            if (!title) {
                alert('Please enter a poll title');
                $('#pollTitle').focus();
                return false;
            }

            switch(pollMode) {
                case 'text':
                    return this.validateTextOptions();
                case 'time':
                    return this.validateTimeOptions();
                case 'custom_timeslot':
                    return this.validateCustomTimeslots();
                default:
                    return false;
            }
        },

        validateTextOptions: function() {
            const options = $('#pollOptionsBox .poll-opt-input');
            let validOptions = 0;

            options.each(function() {
                if ($(this).val().trim()) {
                    validOptions++;
                }
            });

            if (validOptions < 2) {
                alert('Please provide at least 2 valid options');
                return false;
            }

            return true;
        },

        validateTimeOptions: function() {
            const startTime = $('#ptStart').val();
            const endTime = $('#ptEnd').val();
            const isManual = $('#ptManualEnd').is(':checked');

            if (!startTime) {
                alert('Please select start date & time');
                $('#ptStart').focus();
                return false;
            }

            if (isManual && !endTime) {
                alert('Please select end date & time');
                $('#ptEnd').focus();
                return false;
            }

            if (startTime && endTime) {
                const start = new Date(startTime);
                const end = new Date(endTime);

                if (start >= end) {
                    alert('End time must be after start time');
                    return false;
                }
            }

            return true;
        },

        validateCustomTimeslots: function() {
            const timeslots = $('#customTimeslots .timeslot-option');
            let validTimeslots = 0;

            timeslots.each(function() {
                const startTime = $(this).find('.custom-start').val();
                const endTime = $(this).find('.custom-end').val();

                if (startTime && endTime) {
                    const start = new Date(startTime);
                    const end = new Date(endTime);

                    if (start >= end) {
                        alert('End time must be after start time in all timeslots');
                        return false;
                    }

                    validTimeslots++;
                }
            });

            if (validTimeslots === 0) {
                alert('Please provide at least 1 valid timeslot');
                return false;
            }

            return true;
        },

        createPoll: function(event) {
            event.preventDefault();

            if (!this.validateForm()) {
                return;
            }

            const pollData = this.collectPollData();

            const submitBtn = $(event.target);
            const originalText = submitBtn.text();
            submitBtn.text('Creating...').prop('disabled', true);

            setTimeout(() => {
                this.submitPoll(pollData);
                submitBtn.text(originalText).prop('disabled', false);
            }, 1000);
        },

        collectPollData: function() {
            const pollMode = $('#pollMode').val();

            const data = {
                title: $('#pollTitle').val().trim(),
                description: $('#pollDesc').val().trim(),
                type: $('#pollType').val(),
                mode: pollMode,
                created_at: new Date().toISOString()
            };

            switch(pollMode) {
                case 'text':
                    data.options = this.collectTextOptions();
                    break;
                case 'time':
                    data.timeSettings = this.collectTimeSettings();
                    break;
                case 'custom_timeslot':
                    data.timeslots = this.collectCustomTimeslots();
                    break;
            }

            return data;
        },

        collectTextOptions: function() {
            const options = [];
            $('#pollOptionsBox .poll-opt-input').each(function() {
                const value = $(this).val().trim();
                if (value) {
                    options.push(value);
                }
            });
            return options;
        },

        collectTimeSettings: function() {
            return {
                startTime: $('#ptStart').val(),
                endTime: $('#ptEnd').val(),
                manualEnd: $('#ptManualEnd').is(':checked')
            };
        },

        collectCustomTimeslots: function() {
            const timeslots = [];
            $('#customTimeslots .timeslot-option').each(function() {
                const startTime = $(this).find('.custom-start').val();
                const endTime = $(this).find('.custom-end').val();

                if (startTime && endTime) {
                    timeslots.push({
                        startTime: startTime,
                        endTime: endTime
                    });
                }
            });
            return timeslots;
        },

        submitPoll: function(pollData) {

            console.log('Poll data to submit:', pollData);

            alert('Poll created successfully!');

            this.resetForm();

            if (typeof refreshPollList === 'function') {
                refreshPollList();
            }
        },

        resetForm: function() {
            $('#pollTitle').val('');
            $('#pollDesc').val('');
            $('#pollType').val('single');
            $('#pollMode').val('text');

            $('#pollOptionsBox').empty();
            this.addPollOption();
            this.addPollOption();

            $('#ptStart').val('');
            $('#ptEnd').val('');
            $('#ptManualEnd').prop('checked', false);

            $('#customTimeslots').empty();
            this.addCustomTimeslot();

            this.updatePollMode();
        }
    };
});