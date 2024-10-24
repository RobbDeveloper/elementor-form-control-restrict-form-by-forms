<?php
/**
 * Plugin Name: Elementor Form Restrict Submission by other Form Subscriptions
 * Description: Adds a custom control to Elementor Forms for restricting submissions by current submissions in other Forms.
 * Version: 1.0
 * Author: Robb Dev
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

class Elementor_Form_Restrict_Submission_Control {

    public function __construct() {
        // Add custom controls to Elementor form settings
        add_action('elementor/element/form/section_form_fields/after_section_end', [$this, 'add_custom_controls'], 10, 2);
        
        // Hook into the form submission to apply restrictions
        add_action('elementor_pro/forms/process', [$this, 'check_form_restrictions'], 10, 2);
    }

    /**
     * Add custom controls to Elementor form builder.
     */
    public function add_custom_controls($element, $args) {
        // Add the controls directly after the section_form_fields section
        $element->start_controls_section(
            'restrict_section',
            [
                'label' => __('Restrict Send by other Forms', 'elementor-form-restrict'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        // Multi-select field for choosing restricted forms
        $element->add_control(
            'restrict_forms',
            [
                'label' => __('Restricted Forms', 'elementor-form-restrict'),
                'type' => \Elementor\Controls_Manager::SELECT2,
                'multiple' => true,
                'options' => $this->get_elementor_forms(),  // Get available forms dynamically
                'description' => __('Select the forms by which you want to restrict the submission', 'elementor-form-restrict'),
            ]
        );

         // Change restrict_field back to a text field
        $element->add_control(
            'restrict_field',
            [
                'label' => __('Field ID to Restrict', 'elementor-form-restrict'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'placeholder' => __('Enter the field ID (e.g., email)', 'elementor-form-restrict'),
                'description' => __('Enter the field ID with which you want to restrict submissions.', 'elementor-form-restrict'),
            ]
        );

         // Change restrict_field back to a text field
         $element->add_control(
            'restrict_error',
            [
                'label' => __('Error message', 'elementor-form-restrict'),
                'type' => \Elementor\Controls_Manager::WYSIWYG,
                'description' => __('Error message in case the condition is met.', 'elementor-form-restrict'),
            ]
        );

        $element->end_controls_section();
    }

    /**
     * Get Elementor form IDs dynamically from the database.
     */
    private function get_elementor_forms() {
        global $wpdb;

        // Query the Elementor forms
        $results = $wpdb->get_results("
            SELECT element_id, form_name
            FROM {$wpdb->prefix}e_submissions
            WHERE type = 'submission'
            GROUP BY element_id
        ");

        $forms = [];

        // Loop through results and populate the options
        foreach ($results as $row) {
            $forms[$row->element_id] = $row->form_name;
        }

        return $forms;
    }


    /**
     * Check form restrictions on form submission.
     */
    public function check_form_restrictions($record, $handler) {
        global $wpdb;

        // Get form data
		$settings = [];

        $form_data = $record->get_formatted_data();
        $settings['restrict_forms'] = $record->get_form_settings('restrict_forms');
		$settings['restrict_field'] = $record->get_form_settings('restrict_field');
		$settings['restrict_error'] = $record->get_form_settings('restrict_error');

        // Get the restricted forms and field from the form settings
        $restricted_forms = isset($settings['restrict_forms']) ? $settings['restrict_forms'] : [];
        $restrict_field = isset($settings['restrict_field']) ? $settings['restrict_field'] : '';

        if (empty($restricted_forms) || empty($restrict_field)) {
            return; // Skip if no restrictions are set
        }

        // Get the submitted form fields
        $form_fields = $record->get('fields');
        $field_value = isset($form_fields[$restrict_field]['value']) ? $form_fields[$restrict_field]['value'] : '';

        if (empty($field_value)) {
            return; // Skip if the field value is not set
        }

        // Convert restricted forms array to a comma-separated string for SQL
        $restricted_forms_string = implode("','", $restricted_forms);

        // Prepare the query to check if the field value already exists in any restricted forms
        $query = $wpdb->prepare("
            SELECT EXISTS (
                SELECT 1
                FROM {$wpdb->prefix}e_submissions s
                INNER JOIN {$wpdb->prefix}e_submissions_values v ON s.id = v.submission_id
                WHERE s.element_id IN ('$restricted_forms_string')
                AND v.key = %s
                AND v.value = %s
                LIMIT 1
            );
        ", $restrict_field, $field_value);

        // Execute the query
        $submitted = $wpdb->get_var($query);

        // If the email has already been submitted, prevent the form submission
        if ($submitted) {

            $restrict_error = isset($settings['restrict_error']) ?  $settings['restrict_error'] : __('You are already register in an associated form', 'elementor-form-restrict');
            $handler->add_error( $form_data[ 'id' ], $restrict_error );
            $handler->add_error_message( $restrict_error );
            $handler->is_success = false;
			
        }
    }
}

new Elementor_Form_Restrict_Submission_Control();