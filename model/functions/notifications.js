$(document).ready(function() {
  // Initialize DataTable for notifications
  $('#notificationsTable').DataTable({
    order: [[0, 'desc']], // Sort by ID descending (newest first)
    pageLength: 25,
    language: {
      search: "Search notifications:",
      lengthMenu: "Show _MENU_ notifications per page"
    }
  });

  // Handle target type change
  $('#targetType').on('change', function() {
    const targetType = $(this).val();
    
    // Hide all conditional fields
    $('#schoolSelectDiv').hide();
    $('#userEmailDiv').hide();
    
    // Show relevant field based on selection
    if (targetType === 'school') {
      $('#schoolSelectDiv').show();
      $('#schoolSelect').prop('required', true);
      $('#userEmail').prop('required', false);
    } else if (targetType === 'user') {
      $('#userEmailDiv').show();
      $('#userEmail').prop('required', true);
      $('#schoolSelect').prop('required', false);
    } else {
      // broadcast - no additional fields needed
      $('#schoolSelect').prop('required', false);
      $('#userEmail').prop('required', false);
    }
  });

  // Handle send notification form submission
  $('#sendNotificationForm').on('submit', function(e) {
    e.preventDefault();
    
    const submitBtn = $(this).find('button[type="submit"]');
    const originalText = submitBtn.text();
    
    // Disable button and show loading state
    submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Sending...');
    
    const formData = $(this).serialize() + '&action=send_notification';
    
    $.ajax({
      url: 'model/notifications.php',
      type: 'POST',
      data: formData,
      dataType: 'json',
      success: function(response) {
        // Re-enable button
        submitBtn.prop('disabled', false).text(originalText);
        
        if (response.status === 'success') {
          // Show success message
          showToast('Success', response.message, 'success');
          
          // Reset form
          $('#sendNotificationForm')[0].reset();
          $('#targetType').trigger('change'); // Reset conditional fields
          
          // Reload page after a short delay to show new notification in log
          setTimeout(function() {
            window.location.reload();
          }, 1500);
        } else {
          // Show error message
          showToast('Error', response.message || 'Failed to send notification', 'error');
        }
      },
      error: function(xhr, status, error) {
        // Re-enable button
        submitBtn.prop('disabled', false).text(originalText);
        
        // Show error message
        showToast('Error', 'An error occurred while sending the notification. Please try again.', 'error');
        console.error('Ajax error:', status, error);
      }
    });
  });
});

/**
 * Show toast notification
 */
function showToast(title, message, type) {
  // Using Bootstrap toast or fallback to alert
  if (typeof window.showToast === 'function') {
    window.showToast(title, message, type);
  } else {
    // Fallback to browser alert
    alert(title + ': ' + message);
  }
}
