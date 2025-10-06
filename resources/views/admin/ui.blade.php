<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tenant Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold mb-8">Tenant Management</h1>

        <!-- Create Tenant Form -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h2 class="text-xl font-semibold mb-4">Create New Tenant</h2>

            <form id="tenant-form">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Basic Information -->
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700 mb-2">Tenant Name</label>
                        <input type="text" id="name" name="name" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                               placeholder="My Tenant">
                    </div>

                    <div>
                        <label for="identifier" class="block text-sm font-medium text-gray-700 mb-2">Identifier</label>
                        <input type="text" id="identifier" name="identifier" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                               placeholder="my-tenant">
                    </div>
                </div>

                <!-- Preset Selection -->
                <div class="mt-6">
                    <label for="preset" class="block text-sm font-medium text-gray-700 mb-2">Preset</label>
                    <select id="preset" name="preset" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Select a preset...</option>
                    </select>
                </div>

                <!-- Dynamic Configuration Fields -->
                <div id="config-fields" class="mt-6"></div>

                <!-- Submit Button -->
                <div class="mt-6">
                    <button type="submit"
                            class="bg-blue-500 text-white px-6 py-2 rounded-md hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        Create Tenant
                    </button>
                </div>
            </form>
        </div>

        <!-- Existing Tenants -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold mb-4">Existing Tenants</h2>
            <div id="tenants-list">
                <p class="text-gray-500">Loading tenants...</p>
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

            // Load presets on page load
            loadPresets();
            loadTenants();

            // Handle preset selection change
            $('#preset').change(function() {
                const preset = $(this).val();
                if (preset) {
                    loadPresetFields(preset);
                } else {
                    $('#config-fields').empty();
                }
            });

            // Handle form submission
            $('#tenant-form').submit(function(e) {
                e.preventDefault();
                createTenant();
            });

            function loadPresets() {
                $.get('/admin/tenants/presets')
                    .done(function(response) {
                        const presetSelect = $('#preset');
                        presetSelect.empty().append('<option value="">Select a preset...</option>');

                        Object.keys(response.presets).forEach(function(presetKey) {
                            const preset = response.presets[presetKey];
                            presetSelect.append(`<option value="${presetKey}">${preset.name}</option>`);
                        });
                    })
                    .fail(function() {
                        alert('Failed to load presets');
                    });
            }

            function loadPresetFields(preset) {
                $.get(`/admin/tenants/presets/${preset}/fields`)
                    .done(function(response) {
                        const fieldsContainer = $('#config-fields');
                        fieldsContainer.empty();

                        if (response.fields && response.fields.length > 0) {
                            fieldsContainer.append('<h3 class="text-lg font-medium mb-4">Configuration</h3>');

                            const grid = $('<div class="grid grid-cols-1 md:grid-cols-2 gap-6"></div>');

                            response.fields.forEach(function(field) {
                                const fieldHtml = createFieldHtml(field);
                                grid.append(fieldHtml);
                            });

                            fieldsContainer.append(grid);
                        }
                    })
                    .fail(function() {
                        alert('Failed to load preset fields');
                    });
            }

            function createFieldHtml(field) {
                const required = field.required ? 'required' : '';
                const placeholder = field.placeholder ? `placeholder="${field.placeholder}"` : '';

                let inputHtml = '';
                if (field.type === 'textarea') {
                    inputHtml = `<textarea id="config_${field.name}" name="config[${field.name}]" ${required} ${placeholder}
                                          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                          rows="3"></textarea>`;
                } else {
                    inputHtml = `<input type="${field.type}" id="config_${field.name}" name="config[${field.name}]" ${required} ${placeholder}
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">`;
                }

                return `
                    <div>
                        <label for="config_${field.name}" class="block text-sm font-medium text-gray-700 mb-2">
                            ${field.label}
                            ${field.required ? '<span class="text-red-500">*</span>' : ''}
                        </label>
                        ${inputHtml}
                        ${field.description ? `<p class="text-sm text-gray-500 mt-1">${field.description}</p>` : ''}
                    </div>
                `;
            }

            function createTenant() {
                const formData = new FormData($('#tenant-form')[0]);
                const data = {};

                // Convert FormData to object
                for (let [key, value] of formData.entries()) {
                    if (key.startsWith('config[')) {
                        if (!data.config) data.config = {};
                        const configKey = key.match(/config\[(.+)\]/)[1];
                        data.config[configKey] = value;
                    } else {
                        data[key] = value;
                    }
                }

                $.post('/admin/tenants', data)
                    .done(function(response) {
                        if (response.success) {
                            alert('Tenant created successfully!');
                            $('#tenant-form')[0].reset();
                            $('#config-fields').empty();
                            loadTenants();
                        } else {
                            alert('Error: ' + response.error);
                        }
                    })
                    .fail(function(xhr) {
                        if (xhr.responseJSON && xhr.responseJSON.error) {
                            alert('Error: ' + xhr.responseJSON.error);
                        } else {
                            alert('Failed to create tenant');
                        }
                    });
            }

            function loadTenants() {
                $.get('/admin/tenants')
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
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            `);

                            const tbody = table.find('tbody');
                            response.tenants.forEach(function(tenant) {
                                tbody.append(`
                                    <tr>
                                        <td class="border border-gray-300 px-4 py-2">${tenant.name}</td>
                                        <td class="border border-gray-300 px-4 py-2">${tenant.identifier}</td>
                                        <td class="border border-gray-300 px-4 py-2">${tenant.public_id}</td>
                                        <td class="border border-gray-300 px-4 py-2">${new Date(tenant.created_at).toLocaleDateString()}</td>
                                    </tr>
                                `);
                            });

                            tenantsList.append(table);
                        } else {
                            tenantsList.append('<p class="text-gray-500">No tenants found.</p>');
                        }
                    })
                    .fail(function() {
                        $('#tenants-list').html('<p class="text-red-500">Failed to load tenants</p>');
                    });
            }
        });
    </script>
</body>
</html>