=== Moo-Wallet ===

Contributors: Mohammad Farouk

Tags: Moodle, Terawallet

Requires at least: 4.3

Tested up to: 4.3

Stable tag: 4.3

License: GPLv2 or later

License URI: http://www.gnu.org/licenses/gpl-2.0.html
Connecting the woocommerce tera-wallet plugin and coupons with moodle enrollment plugin Wallet Enrollment
== Description ==
This plugin connects the wallet enrollment plugin in moodle with woocommerce plugin TeraWallet, allowing users to use woocommerce coupons in moodle for enrollments and discounts, and using the credits in their tera wallet to get enrol in cources.

Features list including enrol_wallet plugin:

1- Enrollment using wallet balance.
  * Manager creates a course and decide the cost for it.
  * Users can enrol themselves with their credit and the cost deducted from their wallets.

2- Charging wallet by manager (or users with capability) for other users.
  * By default site managers has the capability to add or deduct from a user's wallet balance.
  * Admin can change these capability "enrol/wallet:creditdebit" or grant it to any role.

3- Topping up wallet by users using coupons or payments gateways.
  * Users can charge their wallets by themselves using payments gateways available.
  * Also can use fixed value coupons to do that.
  * Users can review their balance from profile page and topping up their wallets.

4- Direct enrol using coupons or payment gateways.
  * In addition to enrol using wallet credit, user's can be direct enrol themselves using a coupon code it is a 100% discount or fixed with value greater than the course cost.
  * If the coupon with fixed value greater than the course cost, the remaining value will be added to the user's balance.
  * Also if there is payment gateway enabled they can enrol to the course by direct payment.
  * If the user already have a balance for example 20 USD, and the course cost 100 USD, so he will have to pay only 80 to get enrolled and the 20 will be deducted from his balance.

5- Cashback student when purchase a course (optional).
  * Admin can enable a cashback program, so when a user pay for a course, a percentage amount from what he paid will be return to his wallet.

6- Awarding students in a given course if they completed the course with high mark (optional).
  * Encourage your students by awarding them for completing a course.
  * In each course, the course creator can enable awarding program with a certain condition and amount.
  * For example set the condition for 80, means that only students completed the course with 80% or more of the fullmark of the course will get awarded.
  * Setting up the value for 0.2 USD for awarding means that for every raw mark the student get above the condition will add 0.2 to his wallet (student grade is 900 out of max grade for the course 1000, this is a 100 grade above the condition, so 20 USD award added to his wallet).

7- Generate coupons with limiting the usage, and time.
  * If you use moodle as a wallet source, you can add a coupon manually or generate any number you need of coupons.
  * Coupons could be of type fixed of percent.
  * Determine the interval of time at which the coupon could be used or just anytime.
  * Determine the maximum usage for each coupon.
  * Only users with capability "enrol/wallet:createcoupon" could generate coupons and with "enrol/wallet:deletecoupon" can delete coupons.
    note: Editing coupons is not an option now but I'll try to add it in the future.
  * You can choose the length of random coupon, the type of characters in the generated random coupon (lowercase, uppercase and digits).

8- Admin can switch to use woocommerce Tera wallet and woocommerce coupons.
  * If you use woocommerce as a wallet source, so you can't use moodle coupons.
  * Instate you use woocommerce coupons so you can generate and create it their.

9- Cohorts restrictions.
  * In each enrol_wallet instance, course creator can decide if only users in a certain course can enrol (using any of previous methods) or not.

10- Another course enrollment restriction.
  * Course creator can decide to restrict using wallet enrollment so only users enrolled in another selected course can enrol themselves in this course.

11- Display the transactions of wallet.
  * Users with capability "enrol/wallet:transactions" can see all wallet transaction can review any transaction in the website.
  * Other user can see only their own transactions.
  * Using Wallet Balance block plugin to allow users to see their balance anywhere and recharge it by payment.

12- Bulk edit all enrolments in selected courses.
  * Their is an option for admins to edit all users enrollments in selected courses in bulk from a central place.

13- Bulk edit all wallet enrolment instances in selected courses.
  * Admins can edit all wallet enrolment instances in all or selected courses from a central place.

14- Enable gifts as wallet credits upon creation of a user.
  * From settings, admins can enable new user gift program.
  * This gives new users a balance in their wallet as a gift for joining the website.

15- Discounts on courses for specific users depend on custom profile field.
  Want to give certain student a discount 50%?
  Or another student want to give him courses for free?
  If yes, create a custom profile field and make it locked, also invisible if needed.
  In wallet enrolment setting, select this field as a discount field.
  Users with 20 in this field will get 20% discount in all courses.
  Users with 100 or 'free' in this field will get courses for free.
  discounts according to profile field

16- Conditional discounts.
  * In the latest version a conditional discount rules added.
  * Admin can enable or disable conditional discounts.
  * Conditional discount applied for charging the wallet only.
  * Add a rule which is an amount for charging wallet, if the user charge their wallet with a value exceed the rule, the discount applied.
  * Decide the percentage amount for this discount.
  * Discount appear on confirmation when user top-up their wallet using payment along with the refund policy (if admin left it blank nothing appears).
  * Also discount appear to users with capability 'creditdebit' as a final calculated value when they try to recharge other user wallet.

17- Refunding policy.
  * Admins can customize a refunding policy to display it to users.
  * Users can see how much of their wallet balance is refundable.
  * All gifts, cashback, credit from discount and awards are not refundable.
  * Admin can set a grace time period for refunding, after this time is over the balance turn to be nonrefundable (14 days by default).
  * Setting grace period to 0 means that is no grace period and no transformations for the balance.
  * If Admin unchecked 'enable refund' so all balance now on will be nonrefundable.

18- Notifications for every transaction.
  * Users gets a notifications for every debit or credit type of transaction in their wallet.
  * Admins can change the way users get notify from messages setting.

19- Events.
  * Almost any action in this plugin triggers its own event.
  * Transactions events: with every credit or debit action to users wallet.
  * Using coupon: if a coupon used it triggers its own event.
  * Cashback: if a user receive a cashback.
  * Award: If a user get a high mark in a course and receive award for it.
  * Gift: If a new user get gifted.
These events helps administrators or managers to track the wallet workflow.

20- Enhanced security.
  * In the latest version, connection to wordpress is secure using encrypted data.
  * Also using shared secret key which the admin must match those in moodle and wordpress in order for secure connection.

21- Login and logout to wordpress (experimental).
  * When a user login or logout from moodle website, automatically logged in or out from wordpress website.
  * Admin can disable this option ofcourse.

== Installation ==
This section describes how to install the plugin and get it working.
1. Upload [`moo-wallet`](https://github.com/fmido88/moo-wallet/archive/refs/heads/main.zip) to the `/wp-content/plugins/` directory

2. Activate the plugin through the 'Plugins' menu in WordPress

* Initial release
