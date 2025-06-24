# Zermatt Apartments Website Plugin

A WordPress plugin containing support functions for https://zermattapartments.ch
Principally some additional validation functions for the booking form

These hooks into [WP Booking System](https://www.wpbookingsystem.com).

Checks:
* Confirm that email addresses match
* No more than 5 or 3 people in Gornerwald, Imperial and Imperial Studio Respectively

Form and field ids hard coded!

Todo List
* Confirm that the validation functions assigned to the correct forms
* Confirm that the field ids correct
* Provide ability to change error strings on setup screen
* Add a custom dynamic tag to correctly tender the order details
* Potentially add a settings screen to allow the maintenance of the tags and/or dynamic discovery
