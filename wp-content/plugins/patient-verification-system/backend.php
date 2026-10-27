<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Add menu and submenu items in the WordPress admin dashboard
add_action('admin_menu', 'mpv_add_admin_menu');

function mpv_add_admin_menu()
{
    add_menu_page(
        'Patient Verification', // Page title
        'Patient Verification', // Menu title
        'manage_options',       // Capability
        'patient_verification', // Menu slug
        'mpv_admin_page',       // Callback function
        'dashicons-forms',      // Icon
        20                      // Position
    );

    add_submenu_page(
        'patient_verification', // Parent slug
        'All Records',          // Page title
        'View Records',         // Menu title
        'manage_options',       // Capability
        'view_patient_records', // Menu slug
        'mpv_view_records_page' // Callback function
    );
}


// Admin page content (Add Patient Verification)
function mpv_admin_page()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'patient_verifications';

    // Handle search request
    $search_results = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mpv_search_submit'])) {
        $search_query = sanitize_text_field($_POST['mpv_search_query']);
        if (!empty($search_query)) {
            $search_results = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table_name 
                 WHERE patient_name LIKE %s 
                    OR doctor_name LIKE %s 
                    OR verification_code LIKE %s",
                '%' . $search_query . '%',
                '%' . $search_query . '%',
                '%' . $search_query . '%'
            ));
        }
    }

    // Handle form submission for adding a new record
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mpv_submit'])) {
        $patient_name = sanitize_text_field($_POST['patient_name']);
        $doctor_name = sanitize_text_field($_POST['doctor_name']);
        $verification_code = sanitize_text_field($_POST['verification_code']);
        $expiry_date = sanitize_text_field($_POST['expiry_date']);

        if (!empty($patient_name) && !empty($doctor_name) && !empty($verification_code) && !empty($expiry_date)) {
            // Check if the entered verification code is already used
            $code_exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE verification_code = %s",
                $verification_code
            ));

            if ($code_exists) {
                echo '<div class="notice notice-error is-dismissible"><p>This verification code is already in use. Please enter a unique code.</p></div>';
            } else {
                // Insert new record
                $wpdb->insert($table_name, array(
                    'patient_name' => $patient_name,
                    'doctor_name' => $doctor_name,
                    'verification_code' => $verification_code,
                    'expiry_date' => $expiry_date
                ));
                echo '<div class="notice notice-success is-dismissible"><p>Patient data saved successfully!</p></div>';
            }
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>Please fill in all fields.</p></div>';
        }
    }



    ?>
    <div class="wrap">
        <h1>Patient Verification</h1>

        <!-- Search Section -->
        <h2>Search Records</h2>
        <form method="post" style="margin-bottom: 20px;">
            <input type="text" name="mpv_search_query"
                placeholder="Search by patient name, doctor name, or verification code" class="regular-text" required>
            <?php submit_button('Search', 'primary', 'mpv_search_submit', false); ?>
        </form>

        <?php if (!empty($search_results)): ?>
            <h3>Search Results</h3>
            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th>Patient Name</th>
                        <th>Doctor Name</th>
                        <th>Verification Code</th>
                        <th>Expiry Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($search_results as $record): ?>
                        <tr>
                            <td><?php echo esc_html($record->patient_name); ?></td>
                            <td><?php echo esc_html($record->doctor_name); ?></td>
                            <td><?php echo esc_html($record->verification_code); ?></td>
                           <td><?php echo esc_html(date('m/d/Y', strtotime($record->expiry_date))); ?></td>

                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

        <?php elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mpv_search_submit'])): ?>
            <p>No results found.</p>
        <?php endif; ?>

        <!-- Add Patient Verification Form -->
        <h2>Add Patient Verification</h2>
        <form method="post">
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="patient_name">Patient Name</label></th>
                    <td>
                        <input name="patient_name" id="patient_name" type="text" class="regular-text"
                            value="<?php echo isset($_POST['patient_name']) ? esc_attr($_POST['patient_name']) : ''; ?>"
                            required>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="doctor_name">Doctor Name</label></th>
                    <td>
                        <input name="doctor_name" id="doctor_name" type="text" class="regular-text"
                            value="<?php echo isset($_POST['doctor_name']) ? esc_attr($_POST['doctor_name']) : ''; ?>"
                            required>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="verification_code">Verification Code</label></th>
                    <td>
                        <input name="verification_code" id="verification_code" type="text" class="regular-text"
                            value="<?php echo isset($_POST['verification_code']) ? esc_attr($_POST['verification_code']) : ''; ?>"
                            required>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="expiry_date">Expiry Date</label></th>
                    <td>
                        <input name="expiry_date" id="expiry_date" type="date" class="regular-text"
                            value="<?php echo isset($_POST['expiry_date']) ? esc_attr($_POST['expiry_date']) : ''; ?>"
                            required>
                    </td>
                </tr>
            </table>
            <?php submit_button('Save Data', 'primary', 'mpv_submit'); ?>
        </form>

    </div>
    <?php
}

// Enqueue admin CSS for specific admin pages
add_action('admin_enqueue_scripts', 'mpv_enqueue_admin_styles');

function mpv_enqueue_admin_styles($hook)
{
    // Only load on the specific admin page (e.g., the Patient Verification page)
    if ($hook === 'toplevel_page_patient_verification' || $hook === 'patient_verification_page_view_patient_records') {
        wp_enqueue_style(
            'mpv-admin-style', // Unique handle for the stylesheet
            plugin_dir_url(__FILE__) . 'admin-style.css', // Path to the CSS file
            array(), // Dependencies
            '1.0.0', // Version
            'all'    // Media
        );
    }
}
// View records page content
function mpv_view_records_page()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'patient_verifications';

    // Handle deletion if requested
    if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
        $record_id = intval($_GET['id']);
        $wpdb->delete($table_name, ['id' => $record_id]);
        echo '<div class="notice notice-success is-dismissible"><p>Record deleted successfully.</p></div>';
    }

    // Fetch records in descending order by ID
    $records = $wpdb->get_results("SELECT * FROM $table_name ORDER BY id DESC");

    ?>
    <div class="wrap">
        <h1>Patient Verification Records</h1>
        <table class="widefat fixed striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Patient Name</th>
                    <th>Doctor Name</th>
                    <th>Verification Code</th>
                    <th>Expiry Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($records)): ?>
                    <?php foreach ($records as $record): ?>
                        <tr>
                            <td><?php echo esc_html($record->id); ?></td>
                            <td><?php echo esc_html($record->patient_name); ?></td>
                            <td><?php echo esc_html($record->doctor_name); ?></td>
                            <td><?php echo esc_html($record->verification_code); ?></td>
                           <td><?php echo esc_html(date('m-d-Y', strtotime($record->expiry_date))); ?></td>
                            <td>
                                <a href="<?php echo admin_url('admin.php?page=edit_patient_record&id=' . intval($record->id)); ?>"
                                    class="button button-primary">Edit</a>
                                <a href="<?php echo admin_url('admin.php?page=view_patient_records&action=delete&id=' . intval($record->id)); ?>"
                                    class="button button-danger"
                                    onclick="return confirm('Are you sure you want to delete this record?');">Delete</a>
                            </td>

                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6">No records found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}




// Edit Page
// Register the edit page in the admin menu
add_action('admin_menu', function () {
    add_submenu_page(
        null, // Hide from menu
        'Edit Patient Record',
        'Edit Patient Record',
        'manage_options',
        'edit_patient_record',
        'mpv_edit_record_page'
    );
});

// Function to display the edit form
// Function to display the edit form
function mpv_edit_record_page()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'patient_verifications';

    // Get record ID from URL
    $record_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

    if (!$record_id) {
        echo '<div class="notice notice-error"><p>Invalid record ID.</p></div>';
        return;
    }

    // Fetch existing record
    $record = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $record_id));

    if (!$record) {
        echo '<div class="notice notice-error"><p>Record not found.</p></div>';
        return;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mpv_update'])) {
        $patient_name = sanitize_text_field($_POST['patient_name']);
        $doctor_name = sanitize_text_field($_POST['doctor_name']);
        $verification_code = sanitize_text_field($_POST['verification_code']);
        $expiry_date = sanitize_text_field($_POST['expiry_date']);

        if (!preg_match('/^\d{7}$/', $verification_code)) {
            echo '<div class="notice notice-error is-dismissible"><p>Verification code must be exactly 7 digits.</p></div>';
        } else {
            $wpdb->update(
                $table_name,
                [
                    'patient_name' => $patient_name,
                    'doctor_name' => $doctor_name,
                    'verification_code' => $verification_code,
                    'expiry_date' => $expiry_date
                ],
                ['id' => $record->id]
            );
            echo '<div class="notice notice-success is-dismissible"><p>Record updated successfully!</p></div>';
        }
    }
    ?>

    <div class="wrap">
        <h1>Edit Patient Record</h1>
        <form method="post" class="mpv-form">
            <div class="mpv-form-group">
                <label for="patient_name">Patient Name</label>
                <input name="patient_name" id="patient_name" type="text" class="mpv-input" value="<?php echo esc_attr($record->patient_name); ?>" required>
            </div>
            <div class="mpv-form-group">
                <label for="doctor_name">Doctor Name</label>
                <input name="doctor_name" id="doctor_name" type="text" class="mpv-input" value="<?php echo esc_attr($record->doctor_name); ?>" required>
            </div>
            <div class="mpv-form-group">
                <label for="verification_code">Verification Code</label>
                <input name="verification_code" id="verification_code" type="text" class="mpv-input" value="<?php echo esc_attr($record->verification_code); ?>" required minlength="7" maxlength="7" pattern="\d{7}" title="Verification code must be exactly 7 digits">
            </div>
            <div class="mpv-form-group">
                <label for="expiry_date">Expiry Date</label>
                <input name="expiry_date" id="expiry_date" type="date" class="mpv-input" value="<?php echo esc_attr($record->expiry_date); ?>" required>
            </div>
            <button type="submit" name="mpv_update" class="mpv-button">Update Record</button>
        </form>
        <a href="<?php echo admin_url('admin.php?page=view_patient_records'); ?>" class="mpv-button-secondary">Back to Records</a>
    </div>
    <?php
}

add_action('admin_enqueue_scripts', function ($hook) {
    if (isset($_GET['page']) && $_GET['page'] === 'edit_patient_record') {
        wp_enqueue_style('mpv-admin-style', plugin_dir_url(__FILE__) . 'admin-style.css');
    }
});