/**
 * Admin Panel JavaScript
 * Handles form submissions for admin settings
 */

$(document).ready(function() {
    
    // Handle database connection test
    $("#test-db-connection").click(function(e) {
        e.preventDefault();
        
        const form = $("#form-database");
        const resultDiv = $("#db-test-result");
        const button = $(this);
        
        // Disable button during test
        button.prop("disabled", true);
        button.html('<i class="fas fa-spinner fa-spin"></i> Testing...');
        
        // Clear previous results
        resultDiv.html("");
        
        // Collect form data
        const formData = form.serialize();
        
        $.ajax({
            url: "api/test-database-connection.php",
            method: "POST",
            data: formData,
            dataType: "json",
            success: function(response) {
                if (response.ok) {
                    let detailsHtml = "";
                    if (response.details) {
                        detailsHtml = `
                            <ul class="mb-0">
                                <li>Database: <strong>${escapeHtml(response.details.database)}</strong></li>
                                <li>Server: <strong>${escapeHtml(response.details.server)}</strong></li>
                                <li>Tables found: <strong>${response.details.tables_found}</strong></li>
                                <li>Prefix: <strong>${escapeHtml(response.details.prefix)}</strong></li>
                            </ul>
                        `;
                    }
                    resultDiv.html(`
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> ${escapeHtml(response.message)}
                            ${detailsHtml}
                        </div>
                    `);
                } else {
                    resultDiv.html(`
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i> ${escapeHtml(response.error)}
                        </div>
                    `);
                }
            },
            error: function(xhr) {
                let errorMsg = "Connection test failed";
                try {
                    const response = JSON.parse(xhr.responseText);
                    errorMsg = response.error || errorMsg;
                } catch (e) {
                    errorMsg = xhr.statusText || errorMsg;
                }
                
                resultDiv.html(`
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i> ${escapeHtml(errorMsg)}
                    </div>
                `);
            },
            complete: function() {
                // Re-enable button
                button.prop("disabled", false);
                button.html('<i class="fas fa-plug"></i> Test Connection');
            }
        });
    });
    
    // Handle database config save
    $("#form-database").submit(function(e) {
        e.preventDefault();
        
        const form = $(this);
        const submitButton = form.find('button[type="submit"]');
        const resultDiv = $("#db-test-result");
        
        // Confirm before saving
        if (!confirm("Are you sure you want to update the database configuration? This will modify the configuration file and may affect site functionality if incorrect.")) {
            return;
        }
        
        // Disable submit button
        submitButton.prop("disabled", true);
        submitButton.html('<i class="fas fa-spinner fa-spin"></i> Saving...');
        
        // Clear previous results
        resultDiv.html("");
        
        $.ajax({
            url: form.attr("action"),
            method: "POST",
            data: form.serialize(),
            dataType: "json",
            success: function(response) {
                if (response.ok) {
                    resultDiv.html(`
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> ${escapeHtml(response.message)}
                            <br><small>You may need to reload the page for changes to take effect.</small>
                        </div>
                    `);
                    
                    // Optionally reload after a delay
                    setTimeout(function() {
                        if (confirm("Configuration saved successfully! Reload the page to apply changes?")) {
                            location.reload();
                        }
                    }, 2000);
                } else {
                    resultDiv.html(`
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i> ${escapeHtml(response.error)}
                        </div>
                    `);
                }
            },
            error: function(xhr) {
                let errorMsg = "Failed to save configuration";
                try {
                    const response = JSON.parse(xhr.responseText);
                    errorMsg = response.error || errorMsg;
                } catch (e) {
                    errorMsg = xhr.statusText || errorMsg;
                }
                
                resultDiv.html(`
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i> ${escapeHtml(errorMsg)}
                    </div>
                `);
            },
            complete: function() {
                // Re-enable submit button
                submitButton.prop("disabled", false);
                submitButton.html('<i class="fas fa-save"></i> Save Database Config');
            }
        });
    });
    
    // Helper function to escape HTML
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
});
