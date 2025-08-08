<?php /** @noinspection ALL */

/*
 * Plugin Name:  Zermatt Apartments Website Support
 * Plugin URI:   https://github.com/stubevan/ZermattApartmentsPlugin
 * Description:  Support functions for the Zermatt Apartments Booking Functions
 * Version:      0.9
 * Author:       Stu Bevan
 * Author URI:   https://mees.st
*/

#register_activation_hook(__FILE__, '');
#register_deactivation_hook( __FILE__, '' );

/*
 * Remove the Posts Entry from the menu - we dont't need it
 */
//function post_remove ()
//{
//    remove_menu_page('edit.php');
//}
//add_action('admin_menu', 'post_remove');
//
///*
// * Remove support for Comments
// */
//add_action( 'admin_menu', 'za_remove_admin_menus' );
//function za_remove_admin_menus() {
//    remove_menu_page( 'edit-comments.php' );
//}
//// Removes from post and pages
//add_action('init', 'za_remove_comment_support', 100);
//
//function za_remove_comment_support() {
//    remove_post_type_support( 'post', 'comments' );
//    remove_post_type_support( 'page', 'comments' );
//}
//// Removes from admin bar
//function za_admin_bar_render() {
//    global $wp_admin_bar;
//    $wp_admin_bar->remove_menu('comments');
//}
//add_action( 'wp_before_admin_bar_render', 'za_admin_bar_render' );

const ZA_ERROR_MESSAGES = array(
    'en' => array(
        'BAD_EMAIL' => 'The Email addresses do not match',
        'TOO_MANY_GUESTS' => 'The Apartment can only sleep %d guests',
    ),
    'fr' => array(
        'BAD_EMAIL' => 'Les adresses e-mail ne correspondent pas',
        'TOO_MANY_GUESTS' => 'L\'appartement ne peut accueillir que %d personnes',
    ),
    'de' => array(
        'BAD_EMAIL' => 'Die E-Mail-Adressen stimmen nicht überein',
        'TOO_MANY_GUESTS' => 'Das Apartment bietet nur Platz für %d Gäste',
    ),
);

add_filter('wpbs_form_validation_1', 'za_validate_5', 10, 2);
add_filter('wpbs_form_validation_6', 'za_validate_5', 10, 2);
add_filter('wpbs_form_validation_5', 'za_validate_2', 10, 2);

function za_validate_5($result, $data) {
    return(za_booking_form_validate(5, $result, $data));
}

function za_validate_2($result, $data) {
    return(za_booking_form_validate(2, $result, $data));
}

/*
 * This based on the following field ids
 * email: 2
 * email confirm: 33
 * adults: 5
 * teenagers: 23
 * children: 24
 */
function za_booking_form_validate($max_guests, $result, $data)
{
    //wp_mail('stu@mees.st', 'var dump', print_r($data, true));
    $language = $data['language'];
    if ($language != 'en' && $language != 'fr' && $language != 'de') {
        $language = 'en';
    }
    $error_messages = ZA_ERROR_MESSAGES[$language];

    $email = null;
    $email_confirm = null;
    $adults = null;
    $teenagers = null;
    $children = null;

    foreach ($data['form_fields'] as $field) {
        if ($field['id'] == 2) {
            $email = wpbs_get_field_value($field);
        } elseif ($field['id'] == 33) {
            $email_confirm = wpbs_get_field_value($field);
        } elseif ($field['id'] == 5) {
            $adults = wpbs_get_field_value($field);
        } elseif ($field['id'] == 23) {
            $teenagers = wpbs_get_field_value($field);
        } elseif ($field['id'] == 24) {
            $children = wpbs_get_field_value($field);
        }
    }

    // Fields are check by the form
    // Handle defaults values
    if ($teenagers == '-') {
        $teenagers = 0;
    }
    if ($children == '-') {
        $children = 0;
    }

    $error_message = null;
    $error_message1 = null;
    $error = false;

    if ($email != $email_confirm) {
        $error_message = $error_messages['BAD_EMAIL'];
        $error = true;
    }

    if ($adults + $teenagers + $children > $max_guests) {
        $error_message1 = sprintf($error_messages['TOO_MANY_GUESTS'], $max_guests);
        if ($error_message === null) {
            $error_message = $error_message1;
        }else {
            $error_message = $error_message . ", " . $error_message1;
        }
        $error = true;
    }

    if ($error) {
        return [
            'error' => true,
            'error_message' => $error_message,
        ];
    }
    return $result;
}
