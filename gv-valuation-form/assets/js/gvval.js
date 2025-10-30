(function ($) {
    'use strict';

    var GV = window.GVValuation || {};

    var SESSION_COOKIE = 'gvval_session';
    var COOKIE_DAYS = 30;

    function uuidv4() {
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
            var r = Math.random() * 16 | 0;
            var v = c === 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
    }

    function setCookie(name, value, days) {
        var expires = '';
        if (days) {
            var date = new Date();
            date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
            expires = '; expires=' + date.toUTCString();
        }
        document.cookie = name + '=' + (value || '') + expires + '; path=/';
    }

    function getCookie(name) {
        var nameEQ = name + '=';
        var ca = document.cookie.split(';');
        for (var i = 0; i < ca.length; i++) {
            var c = ca[i];
            while (c.charAt(0) === ' ') c = c.substring(1, c.length);
            if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length, c.length);
        }
        return null;
    }

    function normalizePhone(phone) {
        if (!phone) {
            return '';
        }
        var digits = phone.replace(/[^0-9+]/g, '');
        var normalized = '';
        if (digits.indexOf('+421') === 0) {
            normalized = '+421' + digits.substring(4).replace(/\D/g, '');
        } else if (digits.indexOf('421') === 0) {
            normalized = '+421' + digits.substring(3).replace(/\D/g, '');
        } else if (digits.charAt(0) === '0') {
            normalized = '+421' + digits.substring(1).replace(/\D/g, '');
        } else if (digits.charAt(0) === '+') {
            normalized = '+' + digits.substring(1).replace(/\D/g, '');
        } else {
            normalized = '+421' + digits.replace(/\D/g, '');
        }
        var numeric = normalized.replace(/\D/g, '');
        if (numeric.length < 10 || numeric.length > 15) {
            return '';
        }
        return normalized;
    }

    function debounce(fn, wait) {
        var timeout;
        return function () {
            var context = this, args = arguments;
            clearTimeout(timeout);
            timeout = setTimeout(function () {
                fn.apply(context, args);
            }, wait);
        };
    }

    function ensureSession() {
        var session = getCookie(SESSION_COOKIE);
        if (!session) {
            session = uuidv4();
            setCookie(SESSION_COOKIE, session, COOKIE_DAYS);
        }
        return session;
    }

    function readySteps($root) {
        var state = $root.data('gvState');
        if (!state) {
            state = {
                currentStep: 0,
                data: {},
                photos: [],
                submitted: false
            };
            $root.data('gvState', state);
        }
        return state;
    }

    function getVisibleSteps(state, $steps) {
        var visible = [];
        var propertyType = state.data.property_type || '';
        $steps.each(function () {
            var stepIndex = parseInt($(this).attr('data-step'), 10);
            if (stepIndex === 5 && propertyType === 'house') {
                return; // skip floor step for house
            }
            visible.push(stepIndex);
        });
        return visible;
    }

    function updateProgress($root) {
        var state = readySteps($root);
        var $steps = $root.find('.gvval-step');
        var visible = getVisibleSteps(state, $steps);
        if (!visible.length) {
            visible = [0];
        }
        var total = visible.length;
        var currentIndex = visible.indexOf(state.currentStep);
        if (currentIndex === -1) {
            currentIndex = 0;
            state.currentStep = visible[0] || 0;
        }
        $root.find('.gvval-total-steps').text(total);
        $root.attr('data-total-steps', total);
        $root.find('.gvval-current-step').text(currentIndex + 1);
        var percent = total ? ((currentIndex) / (total - 1)) * 100 : 0;
        if (total === 1) {
            percent = 100;
        }
        $root.find('.gvval-progress-fill').css('width', percent + '%');
        $steps.each(function () {
            var index = parseInt($(this).attr('data-step'), 10);
            if (visible.indexOf(index) === -1) {
                $(this).addClass('gvval-hidden');
            } else {
                $(this).removeClass('gvval-hidden');
            }
        });
        $steps.removeClass('gvval-active');
        var $current = $steps.filter('[data-step="' + state.currentStep + '"]');
        $current.addClass('gvval-active');
        $root.find('.gvval-back').toggleClass('gvval-disabled', currentIndex <= 0);
        $root.find('.gvval-next').toggle(visible[visible.length - 1] !== state.currentStep);
        $root.find('.gvval-submit').toggle(visible[visible.length - 1] === state.currentStep);
    }

    function updateAddressLine($root) {
        var city = $.trim($root.find('#address_city').val());
        var street = $.trim($root.find('#address_street').val());
        var number = $.trim($root.find('#address_number').val());
        var parts = [];
        if (street) {
            parts.push(street);
        }
        if (number) {
            parts.push(number);
        }
        if (city) {
            parts.push(city);
        }
        $root.find('#address_line').val(parts.join(' '));
    }

    function collectFormData($root) {
        var state = readySteps($root);
        var data = $.extend(true, {}, state.data);
        var $form = $root.find('.gvval-form');
        var propertyType = $form.find('input[name="property_type"]:checked').val() || '';
        var phone = $.trim($form.find('#phone').val());
        var normalized = normalizePhone(phone);
        data.contact_name = $.trim($form.find('#contact_name').val());
        if (phone) {
            data.phone = phone;
        }
        if (normalized) {
            data.phone_e164 = normalized;
        }
        if (propertyType) {
            data.property_type = propertyType;
        }
        data.address_city = $.trim($form.find('#address_city').val());
        data.address_street = $.trim($form.find('#address_street').val());
        data.address_number = $.trim($form.find('#address_number').val());
        updateAddressLine($root);
        data.address_line = $.trim($form.find('#address_line').val());

        var areaRange = parseInt($form.find('#area_sqm_range').val(), 10);
        var areaInput = parseInt($form.find('#area_sqm_input').val(), 10);
        var area = areaInput || areaRange || 0;
        if (!isNaN(area) && area >= 10 && area <= 200) {
            data.area_sqm = area;
        }
        data.rooms = $form.find('#rooms').val();

        if (propertyType === 'flat') {
            data.floor = $form.find('#floor').val();
            data.has_elevator = $form.find('#has_elevator').is(':checked') ? 1 : 0;
        } else {
            data.floor = '';
            data.has_elevator = 0;
        }

        data.condition = $form.find('input[name="condition"]:checked').val() || '';

        data.has_balcony = $form.find('#has_balcony').is(':checked') ? 1 : 0;
        data.balcony_area = parseInt($form.find('#balcony_area').val(), 10) || '';
        data.has_terrace = $form.find('#has_terrace').is(':checked') ? 1 : 0;
        data.terrace_area = parseInt($form.find('#terrace_area').val(), 10) || '';
        data.has_cellar = $form.find('#has_cellar').is(':checked') ? 1 : 0;
        data.cellar_area = parseInt($form.find('#cellar_area').val(), 10) || '';
        data.parking = $form.find('#parking').val();
        data.parking_slots = parseInt($form.find('#parking_slots').val(), 10) || '';
        if (['reserved_outdoor', 'garage_private', 'garage_inhouse'].indexOf(data.parking) === -1) {
            data.parking_slots = '';
        }

        var accessories = {
            has_balcony: data.has_balcony,
            balcony_area: data.balcony_area,
            has_terrace: data.has_terrace,
            terrace_area: data.terrace_area,
            has_cellar: data.has_cellar,
            cellar_area: data.cellar_area,
            parking: data.parking,
            parking_slots: data.parking_slots
        };
        data.accessories = accessories;

        data.year_built = parseInt($form.find('#year_built').val(), 10) || '';
        data.has_renovation = $form.find('#has_renovation').is(':checked') ? 1 : 0;
        data.year_renovated = data.has_renovation ? (parseInt($form.find('#year_renovated').val(), 10) || '') : '';
        data.heating = $form.find('#heating').val();
        data.heating_other_note = data.heating === 'other' ? $.trim($form.find('#heating_other_note').val()) : '';
        data.extras_text = $.trim($form.find('#extras_text').val());
        data.photos = state.photos || [];
        return data;
    }

    function storeDraft(state, sessionId) {
        try {
            localStorage.setItem('gvval_draft_' + sessionId, JSON.stringify(state.data));
        } catch (e) {
            // ignore
        }
    }

    function loadDraft(sessionId) {
        try {
            var raw = localStorage.getItem('gvval_draft_' + sessionId);
            if (raw) {
                return JSON.parse(raw);
            }
        } catch (e) {
            return {};
        }
        return {};
    }

    function updateLocalDraft($root) {
        var state = readySteps($root);
        if (!state.sessionId) {
            return;
        }
        state.data = collectFormData($root);
        storeDraft(state, state.sessionId);
    }

    function populateYears($select) {
        var currentYear = new Date().getFullYear();
        $select.append($('<option>', { value: '', text: 'Vyberte' }));
        for (var y = currentYear; y >= 1900; y--) {
            $select.append($('<option>', { value: y, text: y }));
        }
    }

    function applyDataToForm($root, data) {
        var $form = $root.find('.gvval-form');
        if (data.contact_name) {
            $form.find('#contact_name').val(data.contact_name);
        }
        if (data.phone) {
            $form.find('#phone').val(data.phone);
        }
        if (data.property_type) {
            $form.find('input[name="property_type"][value="' + data.property_type + '"]').prop('checked', true).trigger('change');
        }
        if (data.address_city) {
            $form.find('#address_city').val(data.address_city);
        }
        if (data.address_street) {
            $form.find('#address_street').val(data.address_street);
        }
        if (data.address_number) {
            $form.find('#address_number').val(data.address_number);
        }
        if (data.area_sqm) {
            $form.find('#area_sqm_range').val(data.area_sqm);
            $form.find('#area_sqm_input').val(data.area_sqm);
            $form.find('.gvval-area-output').text(data.area_sqm);
        }
        if (data.rooms) {
            $form.find('#rooms').val(data.rooms);
            $form.find('.gvval-pill').each(function () {
                var val = $(this).data('value');
                $(this).toggleClass('is-active', val === data.rooms);
            });
        }
        if (data.property_type === 'flat') {
            $form.find('#floor').val(data.floor || '');
            $form.find('#has_elevator').prop('checked', !!parseInt(data.has_elevator, 10));
        }
        $form.find('input[name="condition"][value="' + (data.condition || '') + '"]').prop('checked', true).trigger('change');

        $form.find('#has_balcony').prop('checked', !!parseInt(data.has_balcony, 10)).trigger('change');
        if (data.balcony_area) {
            $form.find('#balcony_area').val(data.balcony_area);
        }
        $form.find('#has_terrace').prop('checked', !!parseInt(data.has_terrace, 10)).trigger('change');
        if (data.terrace_area) {
            $form.find('#terrace_area').val(data.terrace_area);
        }
        $form.find('#has_cellar').prop('checked', !!parseInt(data.has_cellar, 10)).trigger('change');
        if (data.cellar_area) {
            $form.find('#cellar_area').val(data.cellar_area);
        }
        if (data.parking) {
            $form.find('#parking').val(data.parking);
            $form.find('.gvval-parking .gvval-pill').each(function () {
                var val = $(this).data('value');
                $(this).toggleClass('is-active', val === data.parking);
            });
            if (['reserved_outdoor', 'garage_private', 'garage_inhouse'].indexOf(data.parking) > -1) {
                $root.find('[data-toggle="parking"]').removeAttr('hidden');
            } else {
                $root.find('[data-toggle="parking"]').attr('hidden', 'hidden');
            }
        }
        if (data.parking_slots) {
            $form.find('#parking_slots').val(data.parking_slots);
        }
        if (data.year_built) {
            $form.find('#year_built').val(data.year_built);
        }
        if (parseInt(data.has_renovation, 10)) {
            $form.find('#has_renovation').prop('checked', true).trigger('change');
            if (data.year_renovated) {
                $form.find('#year_renovated').val(data.year_renovated);
            }
        }
        if (data.heating) {
            $form.find('#heating').val(data.heating).trigger('change');
        }
        if (data.heating_other_note) {
            $form.find('#heating_other_note').val(data.heating_other_note);
        }
        if (data.extras_text) {
            $form.find('#extras_text').val(data.extras_text);
        }
        if (data.photos && data.photos.length) {
            var state = readySteps($root);
            state.photos = data.photos;
            renderPhotos($root);
        }
        updateAddressLine($root);
        var stateAfter = readySteps($root);
        if (stateAfter.sessionId) {
            stateAfter.data = $.extend(true, {}, data);
            storeDraft(stateAfter, stateAfter.sessionId);
        }
    }

    function renderPhotos($root) {
        var state = readySteps($root);
        var $list = $root.find('.gvval-upload-list');
        $list.empty();
        if (!state.photos) {
            state.photos = [];
        }
        state.photos.forEach(function (item, index) {
            var $thumb = $('<div class="gvval-upload-item">');
            var $img = $('<img>').attr('src', item.url).attr('alt', '');
            var $remove = $('<button type="button" class="gvval-remove">×</button>');
            $remove.on('click', function () {
                state.photos.splice(index, 1);
                renderPhotos($root);
                saveStep($root);
            });
            $thumb.append($img).append($remove);
            $list.append($thumb);
        });
    }

    function saveStep($root) {
        var state = readySteps($root);
        var sessionId = state.sessionId;
        var data = collectFormData($root);
        state.data = data;
        storeDraft(state, sessionId);
        $.ajax({
            url: GV.ajax_url,
            method: 'POST',
            data: {
                action: 'gvval_save_step',
                nonce: GV.nonce,
                session_id: sessionId,
                step: state.currentStep,
                data: data
            }
        }).done(function (resp) {
            if (resp && resp.success && resp.data && resp.data.data) {
                state.data = resp.data.data;
                if (state.data.photos) {
                    state.photos = state.data.photos;
                    renderPhotos($root);
                }
                storeDraft(state, sessionId);
            }
        });
    }

    function trackStep($root) {
        var state = readySteps($root);
        $.post(GV.ajax_url, {
            action: 'gvval_track_step',
            nonce: GV.nonce,
            session_id: state.sessionId,
            step: state.currentStep
        });
    }

    function goToStep($root, targetStep) {
        var state = readySteps($root);
        var $steps = $root.find('.gvval-step');
        var visible = getVisibleSteps(state, $steps);
        if (visible.indexOf(targetStep) === -1) {
            targetStep = visible[visible.length - 1];
        }
        state.currentStep = targetStep;
        updateProgress($root);
        trackStep($root);
    }

    function nextStep($root) {
        var state = readySteps($root);
        var $steps = $root.find('.gvval-step');
        var visible = getVisibleSteps(state, $steps);
        var idx = visible.indexOf(state.currentStep);
        if (idx < visible.length - 1) {
            state.currentStep = visible[idx + 1];
            updateProgress($root);
            trackStep($root);
        }
    }

    function prevStep($root) {
        var state = readySteps($root);
        var $steps = $root.find('.gvval-step');
        var visible = getVisibleSteps(state, $steps);
        var idx = visible.indexOf(state.currentStep);
        if (idx > 0) {
            state.currentStep = visible[idx - 1];
            updateProgress($root);
            trackStep($root);
        }
    }

    function validateStep($root) {
        var state = readySteps($root);
        var step = state.currentStep;
        var data = collectFormData($root);
        var propertyType = data.property_type;
        if (step === 0) {
            return data.contact_name && data.phone;
        }
        if (step === 1) {
            return !!propertyType;
        }
        if (step === 3) {
            return data.area_sqm >= 10 && data.area_sqm <= 200;
        }
        if (step === 4) {
            return !!data.rooms;
        }
        if (step === 5 && propertyType === 'flat') {
            return !!data.floor;
        }
        if (step === 6) {
            return !!data.condition;
        }
        if (step === 8 && data.has_renovation) {
            return !!data.year_renovated;
        }
        if (step === 9) {
            return !!data.heating;
        }
        return true;
    }

    function sendCompletion($root) {
        var state = readySteps($root);
        var sessionId = state.sessionId;
        var data = collectFormData($root);
        state.data = data;
        var $submit = $root.find('.gvval-submit');
        $submit.prop('disabled', true).addClass('is-loading');
        $.ajax({
            url: GV.ajax_url,
            method: 'POST',
            data: {
                action: 'gvval_complete_submission',
                nonce: GV.nonce,
                session_id: sessionId,
                data: data
            }
        }).done(function (resp) {
            if (resp && resp.success) {
                try {
                    localStorage.removeItem('gvval_draft_' + sessionId);
                } catch (e) {
                    // ignore
                }
                if (GV.redirect_url) {
                    window.location.href = GV.redirect_url;
                }
            } else {
                alert(GV.i18n ? GV.i18n.error : 'Chyba');
            }
        }).fail(function () {
            alert(GV.i18n ? GV.i18n.error : 'Chyba');
        }).always(function () {
            $submit.prop('disabled', false).removeClass('is-loading');
        });
    }

    function setupPlaces($root) {
        if (!GV.gmaps_key || typeof google === 'undefined' || !google.maps || !google.maps.places) {
            return;
        }
        var input = $root.find('#address_street')[0];
        if (!input) {
            return;
        }
        var autocomplete = new google.maps.places.Autocomplete(input, {
            types: ['address'],
            componentRestrictions: { country: 'sk' }
        });
        autocomplete.addListener('place_changed', function () {
            var place = autocomplete.getPlace();
            if (!place || !place.address_components) {
                return;
            }
            var city = '', street = '', number = '';
            place.address_components.forEach(function (component) {
                if (component.types.indexOf('locality') > -1 || component.types.indexOf('administrative_area_level_2') > -1) {
                    city = component.long_name;
                }
                if (component.types.indexOf('route') > -1) {
                    street = component.long_name;
                }
                if (component.types.indexOf('street_number') > -1) {
                    number = component.long_name;
                }
            });
            if (city) {
                $root.find('#address_city').val(city);
            }
            if (street) {
                $root.find('#address_street').val(street);
            }
            if (number) {
                $root.find('#address_number').val(number);
            }
            updateAddressLine($root);
        });
    }

    function bindEvents($root) {
        var state = readySteps($root);
        var sessionId = state.sessionId;
        var $form = $root.find('.gvval-form');
        $root.find('.gvval-session').val(sessionId);

        $root.find('#area_sqm_range').on('input change', function () {
            var val = parseInt($(this).val(), 10) || 0;
            $root.find('.gvval-area-output').text(val);
            $root.find('#area_sqm_input').val(val);
        });
        $root.find('#area_sqm_input').on('input change', function () {
            var val = parseInt($(this).val(), 10) || 0;
            if (val < 10) val = 10;
            if (val > 200) val = 200;
            $(this).val(val);
            $root.find('#area_sqm_range').val(val);
            $root.find('.gvval-area-output').text(val);
        });

        $root.find('input[name="property_type"]').on('change', function () {
            var val = $(this).val();
            $root.find('.gvval-card').removeClass('is-active');
            $(this).closest('.gvval-card').addClass('is-active');
            var state = readySteps($root);
            state.data.property_type = val;
            updateProgress($root);
        });

        $root.find('.gvval-pill').on('click', function () {
            var val = $(this).data('value');
            var $group = $(this).closest('.gvval-pills');
            $group.find('.gvval-pill').removeClass('is-active');
            $(this).addClass('is-active');
            if ($group.hasClass('gvval-parking')) {
                $root.find('#parking').val(val);
                if (['reserved_outdoor', 'garage_private', 'garage_inhouse'].indexOf(val) > -1) {
                    $root.find('[data-toggle="parking"]').removeAttr('hidden');
                } else {
                    $root.find('[data-toggle="parking"]').attr('hidden', 'hidden');
                    $root.find('#parking_slots').val('');
                }
            } else {
                $root.find('#rooms').val(val);
            }
        });

        $root.find('input[name="condition"]').on('change', function () {
            $root.find('.gvval-card-stack .gvval-card').removeClass('is-active');
            $(this).closest('.gvval-card').addClass('is-active');
        });

        $root.find('input[type="checkbox"]').on('change', function () {
            var toggle = $(this).attr('id');
            var $target = $root.find('[data-toggle="' + toggle + '"]');
            if ($(this).is(':checked')) {
                $target.removeAttr('hidden');
            } else {
                $target.attr('hidden', 'hidden');
                $target.find('input').val('');
            }
        }).trigger('change');

        $root.find('#parking').val($root.find('#parking').val() || 'none');
        if ($root.find('#parking').val() === 'none') {
            $root.find('[data-toggle="parking"]').attr('hidden', 'hidden');
        }

        $root.find('#heating').on('change', function () {
            if ($(this).val() === 'other') {
                $root.find('[data-toggle="heating-other"]').removeAttr('hidden');
            } else {
                $root.find('[data-toggle="heating-other"]').attr('hidden', 'hidden');
                $root.find('#heating_other_note').val('');
            }
        }).trigger('change');

        $root.find('#has_renovation').on('change', function () {
            if ($(this).is(':checked')) {
                $root.find('[data-toggle="has_renovation"]').removeAttr('hidden');
            } else {
                $root.find('[data-toggle="has_renovation"]').attr('hidden', 'hidden');
                $root.find('#year_renovated').val('');
            }
        }).trigger('change');

        $root.find('#address_city, #address_street, #address_number').on('input change', function () {
            updateAddressLine($root);
        });

        $root.find('input, select, textarea').not('#gvval_photos').on('input change', function () {
            updateLocalDraft($root);
        });

        var phoneSaver = debounce(function () {
            var name = $.trim($root.find('#contact_name').val());
            var phone = $.trim($root.find('#phone').val());
            var normalized = normalizePhone(phone);
            if (!phone || !normalized) {
                return;
            }
            $.ajax({
                url: GV.ajax_url,
                method: 'POST',
                data: {
                    action: 'gvval_save_phone',
                    nonce: GV.nonce,
                    session_id: state.sessionId,
                    phone: phone,
                    name: name
                }
            }).done(function (resp) {
                if (resp && resp.success && resp.data && resp.data.phone_e164) {
                    state.data.phone_e164 = resp.data.phone_e164;
                }
            });
        }, 800);

        $root.find('#phone, #contact_name').on('input', phoneSaver);

        $root.find('.gvval-next').on('click', function (e) {
            e.preventDefault();
            if (!validateStep($root)) {
                alert(GV.i18n ? GV.i18n.error : 'Prosím vyplňte povinné polia');
                return;
            }
            saveStep($root);
            nextStep($root);
        });

        $root.find('.gvval-back').on('click', function (e) {
            e.preventDefault();
            saveStep($root);
            prevStep($root);
        });

        $form.on('submit', function (e) {
            e.preventDefault();
            if (!validateStep($root)) {
                alert(GV.i18n ? GV.i18n.error : 'Prosím vyplňte povinné polia');
                return;
            }
            saveStep($root);
            sendCompletion($root);
        });

        var $fileInput = $root.find('#gvval_photos');
        $fileInput.on('change', function (e) {
            var files = e.target.files;
            if (!files || !files.length) {
                return;
            }
            var state = readySteps($root);
            if (!state.photos) {
                state.photos = [];
            }
            var maxFiles = 20 - state.photos.length;
            var toUpload = Math.min(files.length, maxFiles);
            if (toUpload <= 0) {
                alert('Dosiahli ste limit fotografií.');
                return;
            }
            var loader = $root.find('.gvval-upload-loader');
            loader.removeAttr('hidden');
            var uploads = 0;
            for (var i = 0; i < toUpload; i++) {
                (function (file) {
                    var formData = new FormData();
                    formData.append('action', 'gvval_upload_photo');
                    formData.append('nonce', GV.nonce);
                    formData.append('file', file);
                    $.ajax({
                        url: GV.ajax_url,
                        method: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false
                    }).done(function (resp) {
                        if (resp && resp.success && resp.data) {
                            state.photos.push(resp.data);
                            renderPhotos($root);
                        }
                    }).always(function () {
                        uploads++;
                        if (uploads === toUpload) {
                            loader.attr('hidden', 'hidden');
                            saveStep($root);
                        }
                    });
                })(files[i]);
            }
            $fileInput.val('');
        });

        trackStep($root);
    }

    function initForm($root) {
        if ($root.data('gvvalInit')) {
            return;
        }
        $root.data('gvvalInit', true);
        var sessionId = ensureSession();
        var state = readySteps($root);
        state.sessionId = sessionId;
        state.data = loadDraft(sessionId) || {};
        populateYears($root.find('#year_built'));
        populateYears($root.find('#year_renovated'));
        bindEvents($root);
        applyDataToForm($root, state.data);
        setupPlaces($root);
        updateProgress($root);
    }

    function scan() {
        $('.gvval-wrapper').each(function () {
            initForm($(this));
        });
    }

    $(document).ready(function () {
        scan();
        var observer = new MutationObserver(function () {
            scan();
        });
        observer.observe(document.body, { childList: true, subtree: true });
    });

})(jQuery);
