// Function to fetch schools from the API
function fetchSchools2() {
	$.ajax({
		type: 'GET',
		url: 'model/getInfo.php',
		data: { get_data: 'schools' },
		success: function (response) {
			var school_select = $('#school');

			// Filter out deactivated schools and then sort the remaining schools
			const sortedSchools = response.schools
				.filter(school => school.status === 'active')
				.sort((a, b) => {
					return a.name.localeCompare(b.name);
				});

			school_select.empty();
			$.each(sortedSchools, function (index, schools) {
				school_select.append($('<option>', {
					value: schools.id,
					text: schools.name
				}));
			});
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
	$("#newSchoolForm")[0].reset();
	$('#newSchoolForm [name="school_id"]').val(0);
	$('#newSchoolModalLabel').text("Add New School");
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

	// Populate modal with dept data
	$('#newDeptForm [name="school_id"]').val(school_id);
	$('#newDeptForm [name="dept_id"]').val(dept_id);
	$('#newDeptForm [name="name"]').val(dept_name);

	// Open modal
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

				fetchDepts(school_id);
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

	fetchDepts(school_id);
});

function fetchDepts(school_id) {
	// Get the button element
	var submitButton = $('#submitBtn2');
	submitButton.html('<span class="spinner-border spinner-border-sm mx-auto" role="status" aria-hidden="true"></span>').attr('disabled', true);

	$.ajax({
		method: 'POST',
		url: 'model/getInfo.php',
		data: { get_data: 'depts', school: school_id},
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
	
				populatedeptTable(sortedDepts, school_id,  '#depts_table');
			}
		},
		complete: function () {
			InitiateDatatable('.dept_table');
			
			submitButton.html('Submit').attr('disabled', false);
		}
	});
}


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
										<a class="dropdown-item viewDept" href="javascript:void(0);" data-id="${dept.id}" data-name="${dept.name}" data-school_id="${school_id}"><i class="bx bx-edit me-1"></i> Edit</a>
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

				fetchDepts(school_id);
			} else {
				showToast('bg-danger', data.message);
			}
		}
	});
});