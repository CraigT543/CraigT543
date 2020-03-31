# CraigT543
<h1>Integration tools for Amelia - WordPress Appointment and Event Booking Plugin and Woocommerce</h1>

<p>I am sharing some functions that help more completely integrate Amelia with Woocommerce.  Out of the box Amelia does ok if a client books on their own.  But if payment is recieved in the office there is no integration.  One must record the payment in both Amelia and in Woocommerce if you need your clients to have access to a paid invoice.</p>

<p>These tools presuppose that you have a website that requires log in prior to booking an appointment.  In my case this is a client portal.  So, the wordpress User ID needs to be linked to the Amelia User ID and the Woocommerce User ID.  To deal with that I have two items, the first is a plugin to migrate all existing wordpress users to be Amelia users.  The second is a function, add_users_on_login() that needs to be added to your functions.php file (in your child theme so it does not get erassed). Or you can make the function into a stand alone plugin.</p>



Several things are necessary 
