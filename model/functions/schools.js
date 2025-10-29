// Function to fetch schools from the API
function fetchSchools2() {
        $.ajax({
                type: 'GET',
                url: 'model/getInfo.php',
                data: { get_data: 'schools' },
                success: function (response) {
                        var school_select = $('#school');
                        var faculty_select = $('#faculty_school');
                        var dept_faculty_select = $('#department_faculty');

                        // Filter out deactivated schools and then sort the remaining schools
                        const sortedSchools = response.schools
                                .filter(school => school.status === 'active')
                                .sort((a, b) => {
                                        return a.name.localeCompare(b.name);
                                });

                        school_select.empty().append($('<option>', {
                                value: 0,
                                text: 'Select School'
                        }));
                        faculty_select.empty().append($('<option>', {
                                value: 0,
                                text: 'Select School'
                        }));

                        if (dept_faculty_select.length) {
                                dept_faculty_select
                                        .empty()
                                        .append($('<option>', { value: 0, text: 'All Faculties' }))
                                        .prop('disabled', true)
                                        .trigger('change');
                        }

                        $.each(sortedSchools, function (index, schools) {
                                var option = $('<option>', {
                                        value: schools.id,
                                        text: schools.name
                                });
                                school_select.append(option.clone());
                                faculty_select.append(option.clone());
                        });

                        school_select.val('0').trigger('change');
                        faculty_select.val('0').trigger('change');
                }
        });
}


function fetchSchools() {
        $.ajax({
                type: 'GET',
                url: 'model/getInfo.php',
                data: { get_data: 'schools' },
                success: function (response) {
                        console.log(response);

                        // Sort schools: Active first, then alphabetical by name
                        const sortedSchools = response.schools.sort((a, b) => {
                                // Prioritize active schools
                                if (a.status === 'active' && b.status !== 'active') return -1;
                                if (a.status !== 'active' && b.status === 'active') return 1;

                                // If both are active or inactive, sort alphabetically by name
                                return a.name.localeCompare(b.name);
                        });

                        if ($.fn.dataTable.isDataTable('.table')) {
                                const dataTableInstance = $('.table').DataTable();
                                dataTableInstance.clear().draw().destroy();
                        }

                        populateschoolTable(sortedSchools, '#schools_table');
                },
                complete: function () {
                        InitiateDatatable('.table');
                }
        });
}


// Function to populate the school table
function populateschoolTable(Schools, tableId) {
        const tableBody = $(tableId); // Target your <tbody>
        tableBody.empty(); // Clear existing rows

        Schools.forEach(school => {
                const row = `
							<tr>
								<td>
									<i class="text-primary me-3"></i> <strong>${school.name}</strong>
								</td>
								<td class="text-uppercase">${school.code}</td>
								<td>${school.total_departments}</td>
								<td>${school.total_students}</td>
                <td><span class="fw-bold badge bg-label-${school.status == 'active' ? 'success">Active' : 'danger">Deactivated'}</span></td>
                <td>
                    <div class="dropstart">
                        <button type="button" class="btn p-0 dropdown-toggle hide-arrow" data-bs-toggle="dropdown" aria-expanded="true">
                            <i class="bx bx-dots-vertical-rounded"></i>
                        </button>
                        <div class="dropdown-menu">
                            <a class="dropdown-item viewSchool" href="javascript:void(0);" data-id="${school.id}" data-name="${school.name}" data-code="${school.code}"><i class="bx bx-edit me-1"></i> Edit</a>
                            <a href="javascript:void(0);" 
															class="dropdown-item deactivate" 
															data-id="${school.id}" 
															data-status="${school.status == 'active' ? (school.total_students == '0' ? 'delete' : 'deactivated') : 'active'}">
															<i class="bx me-1 bx-${school.status == 'active' ? 'trash' : 'check-circle'}"></i> 
															${school.status == 'active' ? (school.total_students == '0' ? 'Delete' : 'Deactivate') : 'Activate'}
														</a>
                        </div>
                    </div>
                </td>
            </tr>
            `;
                tableBody.append(row); // Append each row to the table
        });
}


$(document).on('click', '.new_formBtn', function () {
        const activeTab = $('.nav-link.active').attr('data-bs-target');

        if (activeTab === '#navs-top-faculties') {
                $("#newFacForm")[0].reset();
                $('#newFacForm [name="faculty_id"]').val(0);
                $('#newFacForm [name="school_id"]').val($('#faculty_school').val());
                $('#newFacModalLabel').text("Add New Faculty");
                $('#newFacModal').modal('show');
        } else if (activeTab === '#navs-top-departments') {
                $("#newDeptForm")[0].reset();
                $('#newDeptForm [name="dept_id"]').val(0);
                const school_id = $('#school').val();
                $('#newDeptForm [name="school_id"]').val(school_id);
                $('#newDeptModalLabel').text("Add New Department");
                loadFacultyOptions(school_id);
                $('#newDeptModal').modal('show');
        } else {
                $("#newSchoolForm")[0].reset();
                $('#newSchoolForm [name="school_id"]').val(0);
                $('#newSchoolModalLabel').text("Add New School");
                $('#newSchoolModal').modal('show');
        }
});

$(document).on('click', '.viewSchool', function () {
        $('#newSchoolModalLabel').text("Edit School");

        const school_id = $(this).data('id');
        const school_name = $(this).data('name');
        const school_code = $(this).data('code');

        // Populate modal with school data
        $('#newSchoolForm [name="school_id"]').val(school_id);
        $('#newSchoolForm [name="name"]').val(school_name);
        $('#newSchoolForm [name="code"]').val(school_code);

        // Open modal
        $('#newSchoolModal').modal('show');
});

$(document).on('click', '.viewDept', function () {
        const school_id = $(this).data('school_id');
        const dept_id = $(this).data('id');
        const dept_name = $(this).data('name');
        const faculty_id = $(this).data('faculty_id');

        // Populate modal with dept data
        $('#newDeptForm [name="school_id"]').val(school_id);
        $('#newDeptForm [name="dept_id"]').val(dept_id);
        $('#newDeptForm [name="name"]').val(dept_name);

        loadFacultyOptions(school_id, faculty_id);

        $('#newDeptModalLabel').text("Edit Department");
        $('#newDeptModal').modal('show');
});


$(document).on('submit', '#newDeptForm', function (e) {
        e.preventDefault();

        // Get the button element
        var submitButton = $('#submitBtn3');
        submitButton.html('<span class="spinner-border spinner-border-sm mx-auto" role="status" aria-hidden="true"></span>').attr('disabled', true);

        school_id = $('#newDeptForm [name="school_id"]').val();

        $.ajax({
                url: 'model/department.php',
                method: 'POST',
                data: $(this).serialize(),
                success: function (data) {
                        console.log('Response:', data);
                        if (data.status == 'success') {
                                showToast('bg-success', data.message);

                                const facultyFilter = $('#department_faculty').length ? ($('#department_faculty').val() || 0) : 0;
                                fetchDepts(school_id, facultyFilter);
                                $("#newDeptForm")[0].reset();
                                $('#newDeptModal').modal('hide');
                        } else {
                                showToast('bg-danger', data.message);
                        }
                },
                complete: function () {
                        // Revert button text and re-enable the button
                        submitButton.html('Save Changes').attr('disabled', false);
                }
        });
});


$(document).on('submit', '#newSchoolForm', function (e) {
        e.preventDefault();

        // Get the button element
        var submitButton = $('#submitBtn');
        submitButton.html('<span class="spinner-border spinner-border-sm mx-auto" role="status" aria-hidden="true"></span>').attr('disabled', true);

        $.ajax({
                url: 'model/school.php',
                method: 'POST',
                data: $(this).serialize(),
                success: function (data) {
                        console.log('Response:', data);
                        if (data.status == 'success') {
                                showToast('bg-success', data.message);

                                fetchSchools();
                                fetchSchools2();
                                $("#newSchoolForm")[0].reset();
                                $('#newSchoolModal').modal('hide');
                        } else {
                                showToast('bg-danger', data.message);
                        }
                },
                complete: function () {
                        // Revert button text and re-enable the button
                        submitButton.html('Submit').attr('disabled', false);
                }
        });
});


$(document).on('submit', '#selectSchoolForm', function (e) {
        e.preventDefault();

        school_id = $('#selectSchoolForm [name="school"]').val();
        const faculty_id = $('#selectSchoolForm [name="faculty"]').val() || 0;

        fetchDepts(school_id, faculty_id);
});

function fetchDepts(school_id, faculty_id = 0) {
        // Get the button element
        var submitButton = $('#submitBtn2');
        if (submitButton.length) {
                submitButton.html('<span class="spinner-border spinner-border-sm mx-auto" role="status" aria-hidden="true"></span>').attr('disabled', true);
        }

        $.ajax({
                method: 'POST',
                url: 'model/getInfo.php',
                data: { get_data: 'depts', school: school_id, faculty: faculty_id },
                success: function (response) {
                        console.log('Response:', response);

                        if ($.fn.dataTable.isDataTable('.dept_table')) {
                                const dataTableInstance = $('.dept_table').DataTable();
                                dataTableInstance.clear().draw().destroy();
                        }

                        if (response.status == 'success') {
                                // Sort depts: Active first, then alphabetical by name
                                const sortedDepts = response.departments.sort((a, b) => {
                                        // Prioritize active depts
                                        if (a.status === 'active' && b.status !== 'active') return -1;
                                        if (a.status !== 'active' && b.status === 'active') return 1;

                                        // If both are active or inactive, sort alphabetically by name
                                        return a.name.localeCompare(b.name);
                                });

                                populatedeptTable(sortedDepts, school_id, '#depts_table');
                        }
                },
                complete: function () {
                        InitiateDatatable('.dept_table');

                        if (submitButton.length) {
                                submitButton.html('Search').attr('disabled', false);
                        }
                }
        });
}

// CSV Downloads
$(document).on('click', '#downloadSchools', function () {
        var $btn = $(this);
        var original = $btn.html();
        $.ajax({
                url: 'model/getInfo.php',
                method: 'GET',
                data: { download: 'schools' },
                xhrFields: { responseType: 'blob' },
                beforeSend: function () {
                        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Downloading...');
                },
                success: function (blob, status, xhr) {
                        var disposition = xhr.getResponseHeader('Content-Disposition') || '';
                        var filename = 'schools_' + new Date().toISOString().replace(/[-:T]/g, '').slice(0, 15) + '.csv';
                        var match = /filename="?([^";]+)"?/i.exec(disposition);
                        if (match && match[1]) filename = match[1];
                        var link = document.createElement('a');
                        var url = window.URL.createObjectURL(blob);
                        link.href = url;
                        link.download = filename;
                        document.body.appendChild(link);
                        link.click();
                        setTimeout(function () { window.URL.revokeObjectURL(url); document.body.removeChild(link); }, 100);
                        if (typeof showToast === 'function') showToast('bg-success', 'CSV generated. Download starting...');
                },
                error: function () {
                        if (typeof showToast === 'function') showToast('bg-danger', 'Failed to generate CSV.');
                },
                complete: function () { $btn.prop('disabled', false).html(original); }
        });
});

$(document).on('click', '#downloadFaculties', function () {
        var $btn = $(this);
        var original = $btn.html();
        var school_id = $('#faculty_school').val();
        $.ajax({
                url: 'model/getInfo.php',
                method: 'GET',
                data: { download: 'faculties', school: school_id },
                xhrFields: { responseType: 'blob' },
                beforeSend: function () { $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Downloading...'); },
                success: function (blob, status, xhr) {
                        var disposition = xhr.getResponseHeader('Content-Disposition') || '';
                        var filename = 'faculties_' + new Date().toISOString().replace(/[-:T]/g, '').slice(0, 15) + '.csv';
                        var match = /filename="?([^";]+)"?/i.exec(disposition);
                        if (match && match[1]) filename = match[1];
                        var link = document.createElement('a');
                        var url = window.URL.createObjectURL(blob);
                        link.href = url;
                        link.download = filename;
                        document.body.appendChild(link);
                        link.click();
                        setTimeout(function () { window.URL.revokeObjectURL(url); document.body.removeChild(link); }, 100);
                        if (typeof showToast === 'function') showToast('bg-success', 'CSV generated. Download starting...');
                },
                error: function () { if (typeof showToast === 'function') showToast('bg-danger', 'Failed to generate CSV.'); },
                complete: function () { $btn.prop('disabled', false).html(original); }
        });
});

$(document).on('click', '#downloadDepts', function () {
        var $btn = $(this);
        var original = $btn.html();
        var school_id = $('#school').val();
        var faculty_id = $('#department_faculty').length ? $('#department_faculty').val() : 0;
        $.ajax({
                url: 'model/getInfo.php',
                method: 'GET',
                data: { download: 'depts', school: school_id, faculty: faculty_id },
                xhrFields: { responseType: 'blob' },
                beforeSend: function () { $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Downloading...'); },
                success: function (blob, status, xhr) {
                        var disposition = xhr.getResponseHeader('Content-Disposition') || '';
                        var filename = 'departments_' + new Date().toISOString().replace(/[-:T]/g, '').slice(0, 15) + '.csv';
                        var match = /filename="?([^";]+)"?/i.exec(disposition);
                        if (match && match[1]) filename = match[1];
                        var link = document.createElement('a');
                        var url = window.URL.createObjectURL(blob);
                        link.href = url;
                        link.download = filename;
                        document.body.appendChild(link);
                        link.click();
                        setTimeout(function () { window.URL.revokeObjectURL(url); document.body.removeChild(link); }, 100);
                        if (typeof showToast === 'function') showToast('bg-success', 'CSV generated. Download starting...');
                },
                error: function () { if (typeof showToast === 'function') showToast('bg-danger', 'Failed to generate CSV.'); },
                complete: function () { $btn.prop('disabled', false).html(original); }
        });
});


// Function to populate the dept table
function populatedeptTable(Depts, school_id, tableId) {
        const tableBody = $(tableId); // Target your <tbody>
        tableBody.empty(); // Clear existing rows

        Depts.forEach(dept => {
                const row = `
						<tr>
							<td>
								<i class="text-primary me-3"></i> <strong>${dept.name}</strong>
							</td>
							<td>${dept.total_students}</td>
							<td>${dept.total_hocs}</td>
							<td><span class="fw-bold badge bg-label-${dept.status == 'active' ? 'success">Active' : 'danger">Deactivated'}</span></td>
							<td>
								<div class="dropstart">
									<button type="button" class="btn p-0 dropdown-toggle hide-arrow" data-bs-toggle="dropdown" aria-expanded="true">
										<i class="bx bx-dots-vertical-rounded"></i>
									</button>
									<div class="dropdown-menu">
                                                                                <a class="dropdown-item viewDept" href="javascript:void(0);" data-id="${dept.id}" data-name="${dept.name}" data-school_id="${school_id}" data-faculty_id="${dept.faculty_id}"><i class="bx bx-edit me-1"></i> Edit</a>
										<a href="javascript:void(0);" 
											class="dropdown-item deactivate_dept" 
											data-id="${dept.id}" data-school_id="${school_id}"
											data-status="${dept.status == 'active' ? (dept.total_students == '0' ? 'delete' : 'deactivated') : 'active'}">
											<i class="bx me-1 bx-${dept.status == 'active' ? 'trash' : 'check-circle'}"></i> 
											${dept.status == 'active' ? (dept.total_students == '0' ? 'Delete' : 'Deactivate') : 'Activate'}
										</a>
									</div>
								</div>
							</td>
						</tr>
					`;
                tableBody.append(row);
        });
}


$(document).on('click', '.deactivate', function (e) {
        const school_id = $(this).data('id');
        const status = $(this).data('status');

        // Gather form data
        const schoolData = {
                school_edit: 1,
                school_id: school_id,
                status: status,
        };

        $.ajax({
                url: 'model/school.php',
                method: 'POST',
                data: schoolData,
                success: function (data) {
                        console.log('Response:', data);
                        if (data.status == 'success') {
                                showToast('bg-success', data.message);

                                fetchSchools();
                                fetchSchools2();
                        } else {
                                showToast('bg-danger', data.message);
                        }
                }
        });
});


$(document).on('click', '.deactivate_dept', function (e) {
        const school_id = $(this).data('school_id');
        const dept_id = $(this).data('id');
        const status = $(this).data('status');

        // Gather form data
        const deptData = {
                dept_edit: 1,
                dept_id: dept_id,
                status: status,
        };

        $.ajax({
                url: 'model/department.php',
                method: 'POST',
                data: deptData,
                success: function (data) {
                        console.log('Response:', data);
                        if (data.status == 'success') {
                                showToast('bg-success', data.message);

                                const facultyFilter = $('#department_faculty').length ? ($('#department_faculty').val() || 0) : 0;
                                fetchDepts(school_id, facultyFilter);
                        } else {
                                showToast('bg-danger', data.message);
                        }
                }
        });
});

$(document).on('submit', '#selectFacForm', function (e) {
        e.preventDefault();

        school_id = $('#selectFacForm [name="school"]').val();

        fetchFaculties(school_id);
});

function fetchFaculties(school_id) {
        var submitButton = $('#submitBtnFac');
        if (submitButton.length) {
                submitButton.html('<span class="spinner-border spinner-border-sm mx-auto" role="status" aria-hidden="true"></span>').attr('disabled', true);
        }

        $.ajax({
                method: 'POST',
                url: 'model/getInfo.php',
                data: { get_data: 'faculties', school: school_id },
                success: function (response) {
                        if ($.fn.dataTable.isDataTable('.faculty_table')) {
                                const dataTableInstance = $('.faculty_table').DataTable();
                                dataTableInstance.clear().draw().destroy();
                        }

                        if (response.status == 'success') {
                                const sortedFacs = response.faculties.sort((a, b) => {
                                        if (a.status === 'active' && b.status !== 'active') return -1;
                                        if (a.status !== 'active' && b.status === 'active') return 1;
                                        return a.name.localeCompare(b.name);
                                });

                                populateFacTable(sortedFacs, school_id, '#faculties_table');
                        }
                },
                complete: function () {
                        InitiateDatatable('.faculty_table');
                        if (submitButton.length) {
                                submitButton.html('Search').attr('disabled', false);
                        }
                }
        });
}

function populateFacTable(Facs, school_id, tableId) {
        const tableBody = $(tableId);
        tableBody.empty();

        Facs.forEach(fac => {
                const row = `
                                                <tr>
                                                        <td>
                                                                <i class="text-primary me-3"></i> <strong>${fac.name}</strong>
                                                        </td>
                                                        <td>${fac.total_students}</td>
                                                        <td>${fac.total_hocs}</td>
                                                        <td>${fac.total_departments}</td>
                                                        <td><span class="fw-bold badge bg-label-${fac.status == 'active' ? 'success">Active' : 'danger">Deactivated'}</span></td>
                                                        <td>
                                                                <div class="dropstart">
                                                                        <button type="button" class="btn p-0 dropdown-toggle hide-arrow" data-bs-toggle="dropdown" aria-expanded="true">
                                                                                <i class="bx bx-dots-vertical-rounded"></i>
                                                                        </button>
                                                                        <div class="dropdown-menu">
                                                                                <a class="dropdown-item viewFac" href="javascript:void(0);" data-id="${fac.id}" data-name="${fac.name}" data-school_id="${school_id}"><i class="bx bx-edit me-1"></i> Edit</a>
                                                                                <a href="javascript:void(0);" class="dropdown-item deactivate_fac" data-id="${fac.id}" data-school_id="${school_id}" data-status="${fac.status == 'active' ? 'deactivated' : 'active'}"><i class="bx me-1 bx-${fac.status == 'active' ? 'trash' : 'check-circle'}"></i>${fac.status == 'active' ? 'Deactivate' : 'Activate'}</a>
                                                                        </div>
                                                                </div>
                                                        </td>
                                                </tr>
                                        `;
                tableBody.append(row);
        });
}

$(document).on('click', '.viewFac', function () {
        const school_id = $(this).data('school_id');
        const faculty_id = $(this).data('id');
        const faculty_name = $(this).data('name');

        $('#newFacForm [name="school_id"]').val(school_id);
        $('#newFacForm [name="faculty_id"]').val(faculty_id);
        $('#newFacForm [name="name"]').val(faculty_name);

        $('#newFacModalLabel').text("Edit Faculty");
        $('#newFacModal').modal('show');
});

$(document).on('submit', '#newFacForm', function (e) {
        e.preventDefault();

        var submitButton = $('#submitBtnFacForm');
        submitButton.html('<span class="spinner-border spinner-border-sm mx-auto" role="status" aria-hidden="true"></span>').attr('disabled', true);

        school_id = $('#newFacForm [name="school_id"]').val();

        $.ajax({
                url: 'model/faculty.php',
                method: 'POST',
                data: $(this).serialize(),
                success: function (data) {
                        if (data.status == 'success') {
                                showToast('bg-success', data.message);

                                fetchFaculties(school_id);
                                $("#newFacForm")[0].reset();
                                $('#newFacModal').modal('hide');
                        } else {
                                showToast('bg-danger', data.message);
                        }
                },
                complete: function () {
                        submitButton.html('Save changes').attr('disabled', false);
                }
        });
});

$(document).on('click', '.deactivate_fac', function () {
        const school_id = $(this).data('school_id');
        const faculty_id = $(this).data('id');
        const status = $(this).data('status');

        const facData = {
                faculty_edit: 1,
                faculty_id: faculty_id,
                status: status,
        };

        $.ajax({
                url: 'model/faculty.php',
                method: 'POST',
                data: facData,
                success: function (data) {
                        if (data.status == 'success') {
                                showToast('bg-success', data.message);

                                fetchFaculties(school_id);
                        } else {
                                showToast('bg-danger', data.message);
                        }
                }
        });
});

// Function to load faculties into dept form
function loadFacultyOptions(school_id, selected_id = 0) {
        $.ajax({
                method: 'POST',
                url: 'model/getInfo.php',
                data: { get_data: 'faculties', school: school_id },
                success: function (response) {
                        const select = $('#newDeptForm [name="faculty"]');
                        select.empty();
                        if (response.status == 'success') {
                                $.each(response.faculties, function (index, fac) {
                                        const option = $('<option>', {
                                                value: fac.id,
                                                text: fac.name,
                                                selected: fac.id == selected_id
                                        });
                                        select.append(option);
                                });
                        }
                }
        });
}

// Function to populate the department faculty filter
function loadDeptFacultyFilter(school_id, selected_id = 0) {
        const select = $('#department_faculty');
        if (!select.length) {
                return;
        }

        select.empty().append($('<option>', { value: 0, text: 'All Faculties' }));

        if (!school_id || school_id === '0') {
                select.prop('disabled', true).val('0').trigger('change');
                return;
        }

        $.ajax({
                method: 'POST',
                url: 'model/getInfo.php',
                data: { get_data: 'faculties', school: school_id },
                success: function (response) {
                        if (response.status == 'success') {
                                $.each(response.faculties, function (index, fac) {
                                        const option = $('<option>', {
                                                value: fac.id,
                                                text: fac.name
                                        });
                                        select.append(option);
                                });
                        }
                        if (selected_id) {
                                select.val(String(selected_id));
                        } else {
                                select.val('0');
                        }
                },
                complete: function () {
                        select.prop('disabled', false).trigger('change');
                }
        });
}

$(document).on('change', '#school', function () {
        const schoolId = $(this).val();
        loadDeptFacultyFilter(schoolId);
});
