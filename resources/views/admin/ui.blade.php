<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tenant Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }
        .toast {
            animation: slideIn 0.3s ease-out;
        }
        .toast.dismissing {
            animation: slideOut 0.3s ease-out;
        }
        .tab-button {
            transition: all 0.2s ease;
        }
        .tab-button.active {
            border-bottom-color: #3b82f6;
            color: #3b82f6;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        .config-tab-button {
            transition: all 0.2s ease;
        }
        .config-tab-button.active {
            background-color: #3b82f6;
            color: white;
        }
        .config-tab-content {
            display: none;
        }
        .config-tab-content.active {
            display: block;
        }
    </style>
</head>
<body class="bg-gray-100">
    <!-- Toast Container -->
    <div id="toast-container" class="fixed top-4 right-4 z-50 space-y-2" style="max-width: 400px;"></div>

    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold mb-8">Tenant Management</h1>

        <!-- Tab Navigation -->
        <div class="bg-white rounded-t-lg shadow-md">
            <div class="flex border-b border-gray-200">
                <button type="button" class="tab-button active px-6 py-3 font-medium text-gray-700 border-b-2 border-transparent hover:text-blue-600 hover:border-blue-300" data-tab="list">
                    List
                </button>
                <button type="button" class="tab-button px-6 py-3 font-medium text-gray-700 border-b-2 border-transparent hover:text-blue-600 hover:border-blue-300" data-tab="create">
                    Create New
                </button>
                <button type="button" class="tab-button px-6 py-3 font-medium text-gray-700 border-b-2 border-transparent hover:text-blue-600 hover:border-blue-300 hidden" data-tab="edit" id="edit-tab-button">
                    Edit
                </button>
            </div>
        </div>

        <!-- Tab Content -->
        <div class="bg-white rounded-b-lg shadow-md">
            <!-- List Tab -->
            <div class="tab-content active p-6" id="list-tab">
                <h2 class="text-xl font-semibold mb-4">Existing Tenants</h2>
                <div id="tenants-list">
                    <p class="text-gray-500">Loading tenants...</p>
                </div>
            </div>

            <!-- Create Tab -->
            <div class="tab-content p-6" id="create-tab">
                <h2 class="text-xl font-semibold mb-4">Create New Tenant</h2>
                <form id="create-form">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Basic Information -->
                        <div>
                            <label for="create-name" class="block text-sm font-medium text-gray-700 mb-2">Tenant Name</label>
                            <input type="text" id="create-name" name="name" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   placeholder="My Tenant">
                        </div>

                        <div>
                            <label for="create-identifier" class="block text-sm font-medium text-gray-700 mb-2">Identifier</label>
                            <input type="text" id="create-identifier" name="identifier" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   placeholder="my-tenant">
                        </div>
                    </div>

                    <!-- Preset Selection -->
                    <div class="mt-6">
                        <label for="create-preset" class="block text-sm font-medium text-gray-700 mb-2">Preset</label>
                        <select id="create-preset" name="preset" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Select a preset...</option>
                        </select>
                    </div>

                    <!-- Dynamic Configuration Fields -->
                    <div id="create-config-fields" class="mt-6"></div>

                    <!-- Submit Button -->
                    <div class="mt-6">
                        <button type="submit"
                                class="bg-blue-500 text-white px-6 py-2 rounded-md hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            Create Tenant
                        </button>
                    </div>
                </form>
            </div>

            <!-- Edit Tab -->
            <div class="tab-content p-6" id="edit-tab">
                <h2 class="text-xl font-semibold mb-4">Edit Tenant</h2>
                <form id="edit-form">
                    <input type="hidden" id="edit-tenant-id" name="tenant_id" value="">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Basic Information -->
                        <div>
                            <label for="edit-name" class="block text-sm font-medium text-gray-700 mb-2">Tenant Name</label>
                            <input type="text" id="edit-name" name="name" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   placeholder="My Tenant">
                        </div>

                        <div>
                            <label for="edit-identifier" class="block text-sm font-medium text-gray-700 mb-2">Identifier</label>
                            <input type="text" id="edit-identifier" name="identifier" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   placeholder="my-tenant">
                        </div>
                    </div>

                    <!-- View Mode Toggle -->
                    <div class="mt-6">
                        <div class="flex items-center justify-between mb-4">
                            <label class="block text-sm font-medium text-gray-700">Configuration</label>
                            <button type="button" id="toggle-json-view"
                                    class="px-4 py-2 bg-indigo-500 text-white rounded hover:bg-indigo-600 text-sm">
                                Switch to JSON View
                            </button>
                        </div>
                    </div>

                    <!-- Add Preset Buttons and Update Button (Form View Only) -->
                    <div class="mt-6" id="form-view-controls">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Add Configuration</label>
                        <div class="flex flex-wrap gap-2 items-center">
                            <div id="edit-preset-buttons" class="flex flex-wrap gap-2"></div>
                            <button type="button" id="add-custom-field"
                                    class="px-4 py-2 bg-purple-500 text-white rounded hover:bg-purple-600">
                                Add Custom Field
                            </button>
                            <div class="flex gap-2 ml-auto">
                                <button type="submit"
                                        class="bg-blue-500 text-white px-6 py-2 rounded-md hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    Update Tenant
                                </button>
                                <button type="button" id="cancel-edit"
                                        class="bg-gray-500 text-white px-6 py-2 rounded-md hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-500">
                                    Cancel
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Custom Field Input (hidden by default) -->
                    <div id="custom-field-input" class="mt-4 p-4 bg-gray-50 rounded-lg" style="display: none;">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Custom Field Key (use dot notation, e.g., "custom.api.key")</label>
                        <div class="flex gap-2">
                            <input type="text" id="custom-field-name"
                                   class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   placeholder="e.g., custom.setting.name">
                            <button type="button" id="add-custom-field-confirm"
                                    class="bg-green-500 text-white px-4 py-2 rounded-md hover:bg-green-600">
                                Add
                            </button>
                            <button type="button" id="add-custom-field-cancel"
                                    class="bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600">
                                Cancel
                            </button>
                        </div>
                    </div>

                    <!-- Dynamic Configuration Fields (Form View) -->
                    <div id="edit-config-fields" class="mt-6"></div>

                    <!-- JSON Editor (JSON View) -->
                    <div id="json-editor-container" class="mt-6" style="display: none;">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Raw JSON Configuration</label>
                        <textarea id="json-editor"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 font-mono text-sm"
                                  rows="20"></textarea>
                        <p class="text-sm text-gray-500 mt-2">Edit the JSON directly. Changes will be validated when you switch back to form view or submit.</p>

                        <!-- Update Button for JSON View -->
                        <div class="flex gap-2 mt-4">
                            <button type="submit"
                                    class="bg-blue-500 text-white px-6 py-2 rounded-md hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                Update Tenant
                            </button>
                            <button type="button" id="cancel-edit-json"
                                    class="bg-gray-500 text-white px-6 py-2 rounded-md hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-500">
                                Cancel
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            // Set up CSRF token for AJAX requests
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                    'Accept': 'application/json'
                }
            });

            // Tab switching
            $('.tab-button').click(function() {
                const targetTab = $(this).data('tab');
                switchToTab(targetTab);
            });

            function switchToTab(tabName) {
                // Update tab buttons
                $('.tab-button').removeClass('active');
                $(`.tab-button[data-tab="${tabName}"]`).addClass('active');

                // Update tab content
                $('.tab-content').removeClass('active');
                $(`#${tabName}-tab`).addClass('active');
            }

            // Toast notification function
            function showToast(message, type = 'success') {
                const toast = $(`
                    <div class="toast bg-white rounded-lg shadow-lg p-4 mb-2 flex items-start gap-3 border-l-4 ${type === 'success' ? 'border-green-500' : 'border-red-500'}">
                        <div class="flex-shrink-0">
                            ${type === 'success'
                                ? '<svg class="w-6 h-6 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>'
                                : '<svg class="w-6 h-6 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>'
                            }
                        </div>
                        <div class="flex-1">
                            <p class="text-sm font-medium text-gray-900">${message}</p>
                        </div>
                        <button class="flex-shrink-0 text-gray-400 hover:text-gray-600">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                `);

                // Add close button handler
                toast.find('button').click(function() {
                    dismissToast(toast);
                });

                // Add to container
                $('#toast-container').append(toast);

                // Auto dismiss after 4 seconds
                setTimeout(function() {
                    dismissToast(toast);
                }, 4000);
            }

            function dismissToast(toast) {
                toast.addClass('dismissing');
                setTimeout(function() {
                    toast.remove();
                }, 300);
            }

            // Setup CSRF token for all AJAX requests
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });

            // Load presets on page load
            loadPresets();
            loadTenants();

            // Handle preset selection change (create form)
            $('#create-preset').change(function() {
                const preset = $(this).val();
                if (preset) {
                    loadPresetFields(preset, 'create');
                } else {
                    $('#create-config-fields').empty();
                }
            });

            // Handle form submissions
            $('#create-form').submit(function(e) {
                e.preventDefault();
                submitTenant('create');
            });

            $('#edit-form').submit(function(e) {
                e.preventDefault();
                submitTenant('edit');
            });

            // Handle cancel edit
            $('#cancel-edit').click(function() {
                switchToTab('list');
            });

            $('#cancel-edit-json').click(function() {
                switchToTab('list');
            });

            // Track current view mode
            window.isJsonView = false;

            // Handle JSON view toggle
            $('#toggle-json-view').click(function() {
                if (!window.isJsonView) {
                    // Switch to JSON view
                    switchToJsonView();
                } else {
                    // Switch to form view
                    switchToFormView();
                }
            });

            // Handle custom field buttons
            $('#add-custom-field').click(function() {
                $('#custom-field-input').slideDown();
                $('#custom-field-name').focus();
            });

            $('#add-custom-field-cancel').click(function() {
                $('#custom-field-input').slideUp();
                $('#custom-field-name').val('');
            });

            $('#add-custom-field-confirm').click(function() {
                const fieldName = $('#custom-field-name').val().trim();

                if (!fieldName) {
                    showToast('Please enter a field name', 'error');
                    return;
                }

                // Validate field name (alphanumeric, dots, underscores)
                if (!/^[a-zA-Z0-9_.]+$/.test(fieldName)) {
                    showToast('Field name can only contain letters, numbers, dots, and underscores', 'error');
                    return;
                }

                if (!window.editConfig) {
                    showToast('No tenant config loaded', 'error');
                    return;
                }

                // Add empty value for the custom field
                setNestedValue(window.editConfig, fieldName, '');

                // Re-render fields
                renderConfigFields(window.allConfigFields, window.editConfig, true, 'edit');

                // Hide and reset the input
                $('#custom-field-input').slideUp();
                $('#custom-field-name').val('');

                showToast(`Custom field "${fieldName}" added`, 'success');
            });

            function loadPresets() {
                $.get('presets')
                    .done(function(response) {
                        window.presetsData = response.presets;
                        const presetSelect = $('#create-preset');
                        presetSelect.empty().append('<option value="">Select a preset...</option>');

                        Object.keys(response.presets).forEach(function(presetKey) {
                            const preset = response.presets[presetKey];
                            presetSelect.append(`<option value="${presetKey}">${preset.name}</option>`);
                        });
                    })
                    .fail(function() {
                        showToast('Failed to load presets', 'error');
                    });
            }

            function loadPresetFields(preset, mode) {
                if (window.presetsData && window.presetsData[preset]) {
                    renderConfigFields(window.presetsData[preset].fields, {}, false, mode);
                } else {
                    $.get(`presets/${preset}/fields`)
                        .done(function(response) {
                            renderConfigFields(response.fields, {}, false, mode);
                        })
                        .fail(function() {
                            showToast('Failed to load preset fields', 'error');
                        });
                }
            }

            function renderConfigFields(fields, values = {}, editMode = false, mode = 'create') {
                console.log('renderConfigFields called');
                console.log('- fields count:', fields ? fields.length : 0);
                console.log('- values:', values);
                console.log('- editMode:', editMode);
                console.log('- mode:', mode);

                const fieldsContainer = $(`#${mode}-config-fields`);
                fieldsContainer.empty();

                let fieldsToRender = [];

                if (editMode) {
                    // In edit mode, create fields dynamically from existing config values
                    const configKeys = getAllConfigKeys(values);
                    console.log('Found config keys:', configKeys);

                    fieldsToRender = configKeys.map(function(key) {
                        // Try to find predefined field definition
                        const predefinedField = fields ? fields.find(f => f.name === key) : null;

                        if (predefinedField) {
                            return predefinedField;
                        }

                        // Create a generic field definition
                        return {
                            name: key,
                            label: key.split('.').map(word =>
                                word.charAt(0).toUpperCase() + word.slice(1)
                            ).join(' '),
                            type: 'text',
                            required: false,
                            placeholder: '',
                            description: ''
                        };
                    });
                } else if (fields && fields.length > 0) {
                    // In create mode, use predefined fields
                    fieldsToRender = fields;
                }

                console.log('Fields to render:', fieldsToRender.length);

                if (fieldsToRender.length > 0) {
                    // Group fields by 'group' property
                    const groupedFields = {};
                    fieldsToRender.forEach(function(field) {
                        const group = field.group || 'Other';
                        if (!groupedFields[group]) {
                            groupedFields[group] = [];
                        }
                        groupedFields[group].push(field);
                    });

                    const groups = Object.keys(groupedFields);
                    console.log('Field groups:', groups);

                    if (groups.length > 1) {
                        // Multiple groups - render tabbed interface
                        fieldsContainer.append('<h3 class="text-lg font-medium mb-4">Configuration</h3>');

                        // Create tab buttons
                        const tabButtons = $('<div class="flex gap-2 mb-4 border-b border-gray-200"></div>');
                        groups.forEach(function(group, index) {
                            const button = $(`
                                <button type="button"
                                        class="config-tab-button px-4 py-2 rounded-t-md ${index === 0 ? 'active' : 'bg-gray-100 hover:bg-gray-200'}"
                                        data-config-tab="${mode}-${group}">
                                    ${group}
                                </button>
                            `);
                            tabButtons.append(button);
                        });
                        fieldsContainer.append(tabButtons);

                        // Create tab contents
                        groups.forEach(function(group, index) {
                            const tabContent = $(`<div class="config-tab-content ${index === 0 ? 'active' : ''}" id="${mode}-${group}-tab"></div>`);
                            const grid = $('<div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4"></div>');

                            groupedFields[group].forEach(function(field) {
                                const value = getNestedValue(values, field.name) || '';
                                const fieldHtml = createFieldHtml(field, value, editMode, mode);
                                grid.append(fieldHtml);
                            });

                            tabContent.append(grid);
                            fieldsContainer.append(tabContent);
                        });

                        // Attach tab click handlers
                        fieldsContainer.find('.config-tab-button').click(function() {
                            const targetTab = $(this).data('config-tab');
                            const group = targetTab.replace(`${mode}-`, '');

                            // Update buttons
                            fieldsContainer.find('.config-tab-button').removeClass('active').addClass('bg-gray-100');
                            $(this).removeClass('bg-gray-100').addClass('active');

                            // Update content
                            fieldsContainer.find('.config-tab-content').removeClass('active');
                            $(`#${targetTab}-tab`).addClass('active');
                        });
                    } else {
                        // Single group - render without tabs
                        fieldsContainer.append('<h3 class="text-lg font-medium mb-4">Configuration</h3>');
                        const grid = $('<div class="grid grid-cols-1 md:grid-cols-2 gap-6"></div>');

                        fieldsToRender.forEach(function(field) {
                            const value = getNestedValue(values, field.name) || '';
                            const fieldHtml = createFieldHtml(field, value, editMode, mode);
                            grid.append(fieldHtml);
                        });

                        fieldsContainer.append(grid);
                    }

                    // Attach visibility dropdown change handlers
                    attachVisibilityHandlers(mode);
                } else if (editMode) {
                    fieldsContainer.append('<p class="text-gray-500">No configuration fields set. Use "Add Configuration" buttons to add fields.</p>');
                }
            }

            function attachVisibilityHandlers(mode) {
                const visibilityInfo = {
                    'PRIVATE': '🔒 Private: Laravel only',
                    'PROTECTED': '🛡️ Protected: Laravel + SSR',
                    'PUBLIC': '🌐 Public: Laravel + SSR + Browser'
                };

                $(`#${mode}-config-fields select[name^="visibility["]`).on('change', function() {
                    const fieldName = $(this).attr('name').match(/visibility\[(.+)\]/)[1];
                    const value = $(this).val();
                    $(`#${mode}_visibility_info_${fieldName.replace(/\./g, '\\.')}`).text(visibilityInfo[value]);
                });
            }

            function getAllConfigKeys(obj, prefix = '') {
                let keys = [];

                for (let key in obj) {
                    if (obj.hasOwnProperty(key)) {
                        if (key === '__visibility') continue;

                        const fullKey = prefix ? `${prefix}.${key}` : key;
                        const value = obj[key];

                        if (value !== null && typeof value === 'object' && !Array.isArray(value)) {
                            // Recursively get nested keys
                            keys = keys.concat(getAllConfigKeys(value, fullKey));
                        } else {
                            // This is a leaf value
                            keys.push(fullKey);
                        }
                    }
                }

                return keys;
            }

            function getNestedValue(obj, path) {
                return path.split('.').reduce((current, key) => current?.[key], obj);
            }

            function setNestedValue(obj, path, value) {
                const keys = path.split('.');
                const lastKey = keys.pop();
                const target = keys.reduce((current, key) => {
                    if (!current[key] || typeof current[key] !== 'object') {
                        current[key] = {};
                    }
                    return current[key];
                }, obj);
                target[lastKey] = value;
            }

            const MANDATORY_FIELDS = ['app.name', 'app.url', 'frontend.url'];

            function createFieldHtml(field, value = '', editMode = false, mode = 'create') {
                const isMandatory = MANDATORY_FIELDS.includes(field.name);
                const required = (!editMode && (field.required || isMandatory)) ? 'required' : '';
                const placeholder = field.placeholder ? `placeholder="${field.placeholder}"` : '';
                const escapedValue = $('<div>').text(value).html(); // Escape HTML

                const currentVisibility = (mode === 'edit' && window.editConfig && window.editConfig.__visibility)
                    ? (getNestedValue(window.editConfig.__visibility, field.name) || 'PRIVATE')
                    : 'PRIVATE';

                let inputHtml = '';
                if (field.type === 'textarea') {
                    inputHtml = `<textarea id="${mode}_config_${field.name}" name="config[${field.name}]" ${required} ${placeholder}
                                          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                          rows="3">${escapedValue}</textarea>`;
                } else {
                    inputHtml = `<input type="${field.type}" id="${mode}_config_${field.name}" name="config[${field.name}]" ${required} ${placeholder}
                                        value="${escapedValue}"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">`;
                }

                const visibilityInfo = {
                    'PRIVATE': '🔒 Private: Laravel only',
                    'PROTECTED': '🛡️ Protected: Laravel + SSR',
                    'PUBLIC': '🌐 Public: Laravel + SSR + Browser'
                };

                return `
                    <div class="border border-gray-200 p-4 rounded-lg">
                        <label for="${mode}_config_${field.name}" class="block text-sm font-medium text-gray-700 mb-2">
                            ${field.label}
                            ${(isMandatory || (!editMode && field.required)) ? '<span class="text-red-500">*</span>' : ''}
                            ${isMandatory ? '<span class="text-xs text-red-600 ml-1">(Required)</span>' : ''}
                        </label>
                        ${inputHtml}
                        ${field.description ? `<p class="text-sm text-gray-500 mt-1">${field.description}</p>` : ''}

                        <!-- Visibility selector -->
                        <div class="mt-3">
                            <label for="${mode}_visibility_${field.name}" class="block text-xs font-medium text-gray-600 mb-1">Visibility Level</label>
                            <select id="${mode}_visibility_${field.name}" name="visibility[${field.name}]"
                                    class="w-full px-2 py-1 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="PRIVATE" ${currentVisibility === 'PRIVATE' ? 'selected' : ''}>Private (Laravel only)</option>
                                <option value="PROTECTED" ${currentVisibility === 'PROTECTED' ? 'selected' : ''}>Protected (Laravel + SSR)</option>
                                <option value="PUBLIC" ${currentVisibility === 'PUBLIC' ? 'selected' : ''}>Public (Laravel + SSR + Browser)</option>
                            </select>
                            <p class="text-xs text-gray-500 mt-1" id="${mode}_visibility_info_${field.name}">
                                ${visibilityInfo[currentVisibility]}
                            </p>
                        </div>
                    </div>
                `;
            }

            function submitTenant(mode) {
                const formId = mode === 'create' ? '#create-form' : '#edit-form';
                const formData = new FormData($(formId)[0]);
                const data = {};
                const flatVisibility = {};
                const isEdit = mode === 'edit';
                const tenantId = isEdit ? $('#edit-tenant-id').val() : null;

                // If in JSON view, parse JSON editor content
                if (isEdit && window.isJsonView) {
                    try {
                        const jsonText = $('#json-editor').val();
                        const parsedConfig = JSON.parse(jsonText);

                        data.name = $('#edit-name').val();
                        data.identifier = $('#edit-identifier').val();
                        data.config = parsedConfig;
                    } catch (e) {
                        showToast('Invalid JSON: ' + e.message, 'error');
                        return;
                    }
                } else {
                    // Convert FormData to object (existing logic)
                    for (let [key, value] of formData.entries()) {
                        if (key === 'tenant_id') continue;
                        if (key === 'preset') {
                            data[key] = value;
                        } else if (key.startsWith('config[')) {
                            if (!data.config) data.config = {};
                            const configKey = key.match(/config\[(.+)\]/)[1];
                            // Convert dot notation to nested object
                            setNestedValue(data.config, configKey, value);
                        } else if (key.startsWith('visibility[')) {
                            const visibilityKey = key.match(/visibility\[(.+)\]/)[1];
                            // Only store non-PRIVATE values (PRIVATE is default)
                            if (value !== 'PRIVATE') {
                                flatVisibility[visibilityKey] = value;
                            }
                        } else {
                            data[key] = value;
                        }
                    }
                }

                // Validate mandatory fields
                for (const field of MANDATORY_FIELDS) {
                    const fieldValue = getNestedValue(data.config || {}, field);
                    if (!fieldValue || fieldValue.trim() === '') {
                        showToast(`Mandatory field "${field}" is required`, 'error');
                        return;
                    }
                }

                // Add flat visibility to config (backend will convert) - only for form view
                if (!window.isJsonView || !isEdit) {
                    if (Object.keys(flatVisibility).length > 0) {
                        if (!data.config) data.config = {};
                        data.config.__visibility = flatVisibility;
                    }
                }

                const method = isEdit ? 'PUT' : 'POST';
                const url = isEdit ? `tenants/${tenantId}` : 'tenants';

                $.ajax({
                    url: url,
                    method: method,
                    contentType: 'application/json',
                    data: JSON.stringify(data)
                })
                    .done(function(response) {
                        if (response.success) {
                            showToast(isEdit ? 'Tenant updated successfully!' : 'Tenant created successfully!', 'success');
                            loadTenants();
                            if (!isEdit) {
                                switchToTab('list');
                                $(formId)[0].reset();
                                $(`#${mode}-config-fields`).empty();
                            }
                        } else {
                            showToast('Error: ' + response.error, 'error');
                        }
                    })
                    .fail(function(xhr) {
                        if (xhr.responseJSON && xhr.responseJSON.error) {
                            showToast('Error: ' + xhr.responseJSON.error, 'error');
                        } else if (xhr.responseJSON && xhr.responseJSON.message) {
                            showToast('Error: ' + xhr.responseJSON.message, 'error');
                        } else {
                            showToast(isEdit ? 'Failed to update tenant' : 'Failed to create tenant', 'error');
                        }
                    });
            }


            function loadTenants() {
                $.get('tenants')
                    .done(function(response) {
                        const tenantsList = $('#tenants-list');
                        tenantsList.empty();

                        if (response.tenants && response.tenants.length > 0) {
                            const table = $(`
                                <table class="w-full border-collapse border border-gray-300">
                                    <thead>
                                        <tr class="bg-gray-50">
                                            <th class="border border-gray-300 px-4 py-2 text-left">Name</th>
                                            <th class="border border-gray-300 px-4 py-2 text-left">Identifier</th>
                                            <th class="border border-gray-300 px-4 py-2 text-left">Public ID</th>
                                            <th class="border border-gray-300 px-4 py-2 text-left">Created</th>
                                            <th class="border border-gray-300 px-4 py-2 text-left">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            `);

                            const tbody = table.find('tbody');
                            response.tenants.forEach(function(tenant, index) {
                                const row = $(`
                                    <tr>
                                        <td class="border border-gray-300 px-4 py-2">${tenant.name}</td>
                                        <td class="border border-gray-300 px-4 py-2">${tenant.identifier}</td>
                                        <td class="border border-gray-300 px-4 py-2">${tenant.public_id}</td>
                                        <td class="border border-gray-300 px-4 py-2">${new Date(tenant.created_at).toLocaleDateString()}</td>
                                        <td class="border border-gray-300 px-4 py-2">
                                            <button class="edit-tenant bg-blue-500 text-white px-3 py-1 rounded hover:bg-blue-600"
                                                    data-tenant-index="${index}">
                                                Edit
                                            </button>
                                        </td>
                                    </tr>
                                `);
                                tbody.append(row);
                            });

                            tenantsList.append(table);

                            // Store tenants data globally
                            window.tenantsData = response.tenants;

                            // Add edit click handlers
                            $('.edit-tenant').click(function() {
                                const index = $(this).data('tenant-index');
                                const tenant = window.tenantsData[index];
                                console.log('Edit tenant:', tenant);
                                editTenant(tenant);
                            });
                        } else {
                            tenantsList.append('<p class="text-gray-500">No tenants found.</p>');
                        }
                    })
                    .fail(function() {
                        $('#tenants-list').html('<p class="text-red-500">Failed to load tenants</p>');
                    });
            }

            function editTenant(tenant) {
                console.log('editTenant called with:', tenant);
                console.log('tenant.config:', tenant.config);

                // Show edit tab
                $('#edit-tab-button').removeClass('hidden');
                switchToTab('edit');

                // Populate form
                $('#edit-tenant-id').val(tenant.id);
                $('#edit-name').val(tenant.name);
                $('#edit-identifier').val(tenant.identifier);

                // Store current config globally - handle both object and string
                let config = tenant.config || {};
                if (typeof config === 'string') {
                    try {
                        config = JSON.parse(config);
                    } catch (e) {
                        console.error('Failed to parse config:', e);
                        config = {};
                    }
                }
                window.editConfig = config;
                console.log('Stored config:', window.editConfig);

                // Load all config fields and render preset buttons
                $.get('config-fields')
                    .done(function(response) {
                        console.log('Config fields loaded:', response.fields.length);
                        window.allConfigFields = response.fields;
                        renderConfigFields(response.fields, window.editConfig, true, 'edit');
                        renderPresetButtons();
                    })
                    .fail(function() {
                        showToast('Failed to load config fields', 'error');
                    });
            }

            function renderPresetButtons() {
                if (!window.presetsData) return;

                const container = $('#edit-preset-buttons');
                container.empty();

                Object.keys(window.presetsData).forEach(function(presetKey) {
                    const preset = window.presetsData[presetKey];
                    const button = $(`
                        <button type="button"
                                class="px-4 py-2 bg-green-500 text-white rounded hover:bg-green-600"
                                data-preset="${presetKey}">
                            Add ${preset.name} Fields
                        </button>
                    `);

                    button.click(function() {
                        addPresetFields(presetKey);
                    });

                    container.append(button);
                });
            }

            function addPresetFields(presetKey) {
                if (!window.presetsData || !window.presetsData[presetKey]) return;
                if (!window.allConfigFields || !window.editConfig) return;

                const preset = window.presetsData[presetKey];

                // Add empty values for preset fields that don't exist in current config
                preset.fields.forEach(function(field) {
                    const fieldName = field.name;
                    const currentValue = getNestedValue(window.editConfig, fieldName);

                    if (currentValue === undefined || currentValue === null || currentValue === '') {
                        // Set empty value in config so field will show
                        setNestedValue(window.editConfig, fieldName, '');
                    }
                });

                // Re-render fields with updated config
                renderConfigFields(window.allConfigFields, window.editConfig, true, 'edit');
            }

            function setNestedValue(obj, path, value) {
                const keys = path.split('.');
                let current = obj;

                for (let i = 0; i < keys.length - 1; i++) {
                    if (!(keys[i] in current)) {
                        current[keys[i]] = {};
                    }
                    current = current[keys[i]];
                }

                current[keys[keys.length - 1]] = value;
            }

            function switchToJsonView() {
                if (!window.editConfig) {
                    showToast('No config loaded', 'error');
                    return;
                }

                // Update config from form fields before switching
                updateConfigFromForm();

                // Show JSON editor with formatted config
                $('#json-editor').val(JSON.stringify(window.editConfig, null, 2));
                $('#json-editor-container').show();
                $('#edit-config-fields').hide();
                $('#form-view-controls').hide();
                $('#custom-field-input').hide();
                $('#toggle-json-view').text('Switch to Form View');
                window.isJsonView = true;
            }

            function switchToFormView() {
                // Parse JSON and validate
                try {
                    const jsonText = $('#json-editor').val();
                    const parsedConfig = JSON.parse(jsonText);

                    // Update window.editConfig with parsed JSON
                    window.editConfig = parsedConfig;

                    // Re-render form fields
                    renderConfigFields(window.allConfigFields, window.editConfig, true, 'edit');

                    // Show form view
                    $('#json-editor-container').hide();
                    $('#edit-config-fields').show();
                    $('#form-view-controls').show();
                    $('#toggle-json-view').text('Switch to JSON View');
                    window.isJsonView = false;

                    showToast('Config loaded from JSON', 'success');
                } catch (e) {
                    showToast('Invalid JSON: ' + e.message, 'error');
                }
            }

            function updateConfigFromForm() {
                // Update window.editConfig from form fields
                const config = {};
                const visibility = {};

                $('#edit-form').find('[name^="config["]').each(function() {
                    const name = $(this).attr('name');
                    const configKey = name.match(/config\[(.+)\]/)[1];
                    const value = $(this).val();
                    if (value !== '') {
                        setNestedValue(config, configKey, value);
                    }
                });

                // Collect ALL visibility values (including PRIVATE) and convert to nested structure
                $('#edit-form').find('[name^="visibility["]').each(function() {
                    const name = $(this).attr('name');
                    const visibilityKey = name.match(/visibility\[(.+)\]/)[1];
                    const value = $(this).val();

                    // Store in nested structure to match input format
                    if (value !== 'PRIVATE') {
                        setNestedValue(visibility, visibilityKey, value);
                    }
                });

                // Deep merge config to preserve nested structure
                window.editConfig = deepMerge(window.editConfig, config);

                // Update visibility - replace entirely with new structure
                if (Object.keys(visibility).length > 0) {
                    window.editConfig.__visibility = visibility;
                } else if (window.editConfig.__visibility) {
                    // If no non-PRIVATE visibility collected, remove __visibility
                    delete window.editConfig.__visibility;
                }
            }

            function deepMerge(target, source) {
                const output = { ...target };

                for (const key in source) {
                    if (source.hasOwnProperty(key)) {
                        if (source[key] && typeof source[key] === 'object' && !Array.isArray(source[key])) {
                            if (target[key] && typeof target[key] === 'object' && !Array.isArray(target[key])) {
                                output[key] = deepMerge(target[key], source[key]);
                            } else {
                                output[key] = source[key];
                            }
                        } else {
                            output[key] = source[key];
                        }
                    }
                }

                return output;
            }
        });
    </script>
</body>
</html>