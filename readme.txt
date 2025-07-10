=== Advanced Checkout Tracker ===
Contributors: coderzonebd
Tags: woocommerce, checkout, incomplete orders, abandoned cart, recovery, fraud, tracker, analytics
Stable tag: 1.0.3
Tested up to: 6.8

== Description ==

Advanced Checkout Tracker is a powerful WooCommerce plugin designed to help store owners recover potentially lost sales by tracking incomplete checkouts and providing tools for effective follow-up. It also includes robust fraud prevention features to protect your store from unwanted orders.

**Key Features:**

* **Incomplete Checkout Tracking:** Automatically captures customer information and cart details as they progress through the checkout process, even if they don't complete the purchase. This data is stored locally within your WordPress database.
* **Easy Recovery:** Directly create new WooCommerce orders from incomplete checkout records, allowing you to manually process or follow up with customers.
* **Checkout Management:** Mark incomplete checkouts as "On Hold" for future follow-up or "Cancelled" if they are no longer relevant. You can also re-open cancelled checkouts.
* **Fraud Blocker:** Prevent problematic customers by blocking specific IP addresses, email addresses, or phone numbers from placing orders on your store. This feature operates by checking against your locally managed blocklists.
* **Detailed Analytics Dashboard:** Get a clear overview of your checkout performance, including the number and value of incomplete, recovered, on-hold, and cancelled checkouts.
* **Customer Success Ratio (Local Analytics):** Within the WooCommerce Order list and on individual order pages, view an estimated success ratio for customer phone numbers based on their past local order history. This helps identify potentially risky orders.
* **Blocked Orders Log:** Maintain a log of orders automatically blocked by the system due to configured rules.

**What Data We Collect (Locally):**

The plugin stores the following customer and cart data directly within your WordPress database to enable its core functionality:
* **Customer Details:** First name, last name, email address, phone number, billing address (address 1, address 2, city, state, postcode, country).
* **Technical Information:** Customer's IP address and session ID.
* **Cart Information:** Details of products in the cart (product ID, name, quantity, line total) and the total cart value.
* **Tracking Status:** The status of the incomplete checkout (incomplete, recovered, hold, cancelled), follow-up dates, and any admin notes.
* **Recovered Order ID:** If an incomplete checkout is recovered, the associated WooCommerce Order ID.
* **Blocked Items:** For the Fraud Blocker, it stores blocked IP addresses, email addresses, and phone numbers, along with a reason and the user who added them.
* **Blocked Order Logs:** Details of orders that were automatically blocked by the system due to configured rules (e.g., success ratio).

This data is used exclusively to provide the plugin's features within your WordPress administration area and is not transmitted to external servers without explicit, user-initiated actions.

**License Activation:**

This plugin is designed to be fully functional for free, just share your domain with us we will active your free version as soon as possible. For premium features or dedicated support, you may explore the Pro version available on our website.

== Installation ==

1.  **Upload:**
    * Download the plugin .zip file.
    * Go to your WordPress admin dashboard, navigate to `Plugins > Add New`.
    * Click on "Upload Plugin" and choose the downloaded .zip file.
    * Click "Install Now".
2.  **Activate:**
    * Once installed, click "Activate Plugin".
3.  **Configuration:**
    * After activation, you will find "Checkout Tracker" in your WordPress admin menu.
    * Go to `Checkout Tracker > Settings` and refresh your licence status. To configure data retention, module toggles (like Checkout Tracking, Fraud Blocker), and other preferences.

== Frequently Asked Questions ==

= Does this plugin require WooCommerce? =
Yes, Advanced Checkout Tracker is a WooCommerce extension and requires WooCommerce to be installed and active to function properly.

= Where is the data stored? =
All data collected by this plugin (incomplete checkouts, blocked items, etc.) is stored locally in your WordPress database.

= Is there a premium version? =
Yes, a Pro version with advanced features and dedicated support is available on our website. This free version provides robust functionality without requiring an external license.

== Changelog ==

= 1.0.3 =
* Fix: Blocked order log now deletes entries instantly without requiring a page refresh.
* Fix: Checkout page block notices are now styled correctly and appear immediately without needing a page refresh.
* Fix: Resolved an issue where an icon box (`\e016`) would appear in the block notice due to theme font conflicts.
* Feature: Customer names are now saved and displayed in the Blocked Orders Log and the details view.
* Feature: Added a stylish "Powered by" footer to the Courier Success History section.
* Enhancement: Implemented a robust phone number normalization function to handle various input formats (e.g., `+880`, `01885-660190`, `01885 660190`) and standardize them.
* Enhancement: Inaccurate incomplete checkout counts are now corrected by syncing with the HQ upon order completion.

= 1.0.2 =
* Fix: Updated success ratio blocker logic to correctly handle a grace period of 0.

= 1.0.1 =
* Initial Release.

= 1.0 =
* Incomplete checkout tracking.
* Manual order recovery.
* Fraud Blocker (IP, Email, Phone).
* Dashboard Overview and detailed lists.
* Courier Analytics (local data only).
* Blocked Orders Log.
* Admin settings for data retention and module toggles.