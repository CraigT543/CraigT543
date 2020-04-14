# CraigT543
<h1>Integration tools for Amelia - WordPress Appointment and Event Booking Plugin and Woocommerce</h1>

<p>I am sharing some functions that help more completely integrate Amelia with Woocommerce.  Out of the box Amelia does ok if a client books on their own.  But if payment is recieved in the office there is no integration.  One must record the payment in both Amelia and in Woocommerce if you need your clients to have access to a pay invoices on line.</p>


<h2>The amilia-users-update.zip WP Plugin</h2>
<p>I am a solo practitioner and not a clinic.  So this works great in that context.  If you plan to impliment the functions for your clinic, adjustments may need to be made. These tools presuppose that you have a website that requires log in prior to booking an appointment.  In my case I have a client portal that clients must log in to use.  So, the wordpress User ID needs to be linked to the Amelia User ID and the Woocommerce User ID for all existing clients and for new registers. The first item I have developped is a plugin to migrate all existing wordpress users to be Amelia users: amilia-users-update.zip. You essentially use this plug in once and you are done you can delete it when it has finished. Load it manually.  Activate it.  And you will then see it in the settings portion of your wp-admin dashboard as "Amelia User Update".  When you click into that you will be brought to a page with one button that says "Update Amelia Users Table".  Push that button and then all current wp users are now imported into Amelia as users. You now can deactivate and delete the plug in.</p>

<h2>WP functions.php</h2>
<p>In the functions.php file I have a group of functions that can be pasted into your child theme's functions.php file.  Or you can make it into a plug in.  Both will work.  I recommend the plugin method.</p>

<h3>TABLE OF CONTENTS</h3>
<ol>
    <li>Add users on login
    <li>Schedule Hook to Run update_my_wc_orders() at least daily</li>
    <li>My Amelia Orders Webhook Endpoint</li>
    <li>Function to Update WC Orders to Reflect Amelia Appointments Payment Status -- update_my_wc_orders()</li>
    <li>Function to Update Amelia Orders to Reflect Customer On-line Payment</li>
</ol>
<p>The first function, add_users_on_login() will make any new regestered user an Amelia user as well. The schedule hook assumes that you are running WP-Cron (recommended but not required).  The Amelia Orders Webhook requires that you add the webhook into Amelia's admin pannel at settings>integrations>webhooks. Fill in the form thus:</p>
<ul>
    <li>Name: Update WC Orders</li>
    <li>URL: http://www.YOURWEBSITE.com/wp-json/myameliaorder/v1/author/(?P\d+)</li>  
    <li>Type: Appointment</li>  
    <li>Action: Booking Completed</li>  
</ul>
<p>Click to save this and it will run when an appointment booking is completed. Webhooks have a bit of a delay so wait a few minutes and you will see the order created in Woocommerce.  If you have WP-Cron running, every night it will update any changes that you have made in your appointments durring the day.  And it will update any time you enter payments in amelia on the admin pannel and mark them as paid.  This will then be reflected in Woocommerce.</p>
<p>The last function updates Amelia if a customer makes an on-line payment to the Wordpress Orders Pannel</p>
