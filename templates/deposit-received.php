<?php
/**
 * "Deposit Received" text, used for both the automatic form notification and the manual
 * Settings → Email → Email Templates entries. Tags are WP Booking System email tags.
 *
 * Only used by `wp zermatt deposit-email install`. Once installed, Nicki edits the text in wp-admin;
 * changing this file does not change emails that already exist.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'en' => array(
		'subject' => 'Deposit received for your stay at {Calendar Title}',
		'message' => "Dear {1:First Name},

Thank you, I have received your deposit and your booking for {Calendar Title} from {Start Date} to {End Date} is now secured.

The remaining balance of {Outstanding Amount} is due before your arrival; I'll send you a reminder nearer the time.

If you have any questions in the meantime, just reply to this email.

Thanks,

Nicki",
	),
	'fr' => array(
		'subject' => 'Acompte reçu pour votre séjour à {Calendar Title}',
		'message' => "Cher/Chère {1:First Name},

Merci, j'ai bien reçu votre acompte et votre réservation pour {Calendar Title} du {Start Date} au {End Date} est désormais confirmée.

Le solde de {Outstanding Amount} est à régler avant votre arrivée ; je vous enverrai un rappel le moment venu.

Si vous avez des questions d'ici là, il vous suffit de répondre à cet e-mail.

Merci,

Nicki",
	),
	'de' => array(
		'subject' => 'Anzahlung erhalten für Ihren Aufenthalt im {Calendar Title}',
		'message' => "Sehr geehrte/r {1:First Name},

vielen Dank, ich habe Ihre Anzahlung erhalten. Ihre Buchung für {Calendar Title} vom {Start Date} bis {End Date} ist damit gesichert.

Der Restbetrag von {Outstanding Amount} ist vor Ihrer Anreise fällig; ich schicke Ihnen rechtzeitig eine Erinnerung.

Wenn Sie in der Zwischenzeit Fragen haben, antworten Sie einfach auf diese E-Mail.

Vielen Dank,

Nicki",
	),
);
