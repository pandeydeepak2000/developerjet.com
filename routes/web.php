<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\EmailOtpController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\BusinessAutomationController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\CustomerApiKeyController;
use App\Http\Controllers\CustomerAutomationController;
use App\Http\Controllers\CustomerInvoiceController;
use App\Http\Controllers\CustomerOrderController;
use App\Http\Controllers\CustomerProfileController;
use App\Http\Controllers\CustomerProjectController;
use App\Http\Controllers\CustomerRequestController;
use App\Http\Controllers\CustomerSubscriptionController;
use App\Http\Controllers\CustomerTicketController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocsController;
use App\Http\Controllers\MarketingController;
use App\Http\Controllers\ServicePanelController;
use App\Http\Controllers\ServicesController;
use App\Http\Controllers\Staff;
use App\Http\Controllers\WalletTopupController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

// Public marketing pages
Route::get('/', [MarketingController::class, 'home'])->name('home');
Route::view('/hermes', 'pages.hermes-overview')->name('hermes');
Route::get('/hermes-cli', [CatalogController::class, 'hermesCli'])->name('hermes.cli');
Route::get('/hermes-workspace', [CatalogController::class, 'hermesWorkspace'])->name('hermes.workspace');
Route::get('/n8n', [CatalogController::class, 'n8n'])->name('n8n');
Route::get('/plans', [CatalogController::class, 'index'])->name('plans');
Route::get('/ai-all-access', [MarketingController::class, 'aiAllAccess'])->name('ai-all-access');
Route::get('/compare', [MarketingController::class, 'compareIndex'])->name('compare.index');
Route::get('/compare/{slug}', [MarketingController::class, 'compare'])->where('slug', '[a-z0-9-]+')->name('compare.show');
Route::get('/digital-services', [MarketingController::class, 'digitalServices'])->name('digital-services');
Route::get('/ai-applications', [MarketingController::class, 'aiApplicationHosting'])->name('ai-app-hosting');
// Business Automation: public page + guest-friendly requirement form (CSRF, rate limits, honeypot, timing token).
Route::get('/business-automation', [BusinessAutomationController::class, 'show'])->name('business-automation');
Route::post('/business-automation', [BusinessAutomationController::class, 'store'])->middleware('throttle:automation-requests')->name('business-automation.store');
Route::get('/business-automation/thank-you', [BusinessAutomationController::class, 'thanks'])->name('business-automation.thanks');
Route::get('/{product}', [MarketingController::class, 'product'])->whereIn('product', ['claude-code', 'docker-vps', 'openclaw', 'ai-complete-bundle'])->name('product.show');
Route::view('/about', 'pages.about')->name('about');
Route::view('/contact', 'pages.contact')->name('contact');
Route::view('/terms', 'pages.terms')->name('terms');
Route::view('/privacy', 'pages.privacy')->name('privacy');
Route::view('/refund', 'pages.refund')->name('refund');

// Documentation centre
Route::get('/docs', [DocsController::class, 'index'])->name('docs.index');
Route::get('/docs/{slug}', [DocsController::class, 'show'])->where('slug', '[a-z0-9-]+')->name('docs.show');

// Professional services (database-driven)
Route::get('/services/{page}', [ServicesController::class, 'show'])->whereIn('page', array_keys(ServicesController::PAGES))->name('services.show');

// Payment gateway webhooks: authenticated by signature, CSRF-exempt (see bootstrap/app.php)
Route::post('/webhooks/{gateway}', [WebhookController::class, 'handle'])->middleware('throttle:240,1')->whereIn('gateway', ['razorpay'])->name('webhooks');

// Guest-only authentication
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:10,1');
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:6,1');
    Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'email'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->middleware('throttle:5,1')->name('password.update');
});

Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

// Email OTP verification (authenticated, not yet verified)
Route::middleware('auth')->group(function () {
    Route::get('/email/verify', [EmailOtpController::class, 'notice'])->name('verification.notice');
    Route::post('/email/verify', [EmailOtpController::class, 'verify'])->middleware('throttle:10,1')->name('verification.verify');
    Route::post('/email/verify/resend', [EmailOtpController::class, 'resend'])->middleware('throttle:6,1')->name('verification.send');
});

// Cart: open to guests, persisted per session and merged into the customer's cart on login
Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::post('/cart', [CartController::class, 'store'])->middleware('throttle:30,1')->name('cart.store');
Route::delete('/cart/{item}', [CartController::class, 'destroy'])->whereNumber('item')->name('cart.destroy');

// Verified customers only
Route::middleware(['auth', 'verified', 'impersonation.readonly'])->group(function () {
    Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout');
    Route::post('/checkout', [CheckoutController::class, 'store'])->middleware('throttle:20,1')->name('checkout.store');

    Route::get('/services/{plan:slug}/request', [ServicesController::class, 'create'])->name('services.request.create');
    Route::post('/services/{plan:slug}/request', [ServicesController::class, 'store'])->middleware('throttle:10,1')->name('services.request.store');

    Route::prefix('dashboard')->group(function () {
        Route::get('/', [DashboardController::class, 'overview'])->name('dashboard');
        Route::get('/orders', [DashboardController::class, 'orders'])->name('dashboard.orders');
        Route::get('/orders/{order}', [CustomerOrderController::class, 'show'])->name('dashboard.orders.show');
        Route::post('/orders/{order}/verify/{gateway}', [CustomerOrderController::class, 'verify'])->middleware('throttle:20,1')->whereIn('gateway', ['razorpay'])->name('dashboard.orders.verify');
        Route::delete('/orders/{order}', [CustomerOrderController::class, 'cancel'])->name('dashboard.orders.cancel');

        Route::get('/services', [DashboardController::class, 'services'])->name('dashboard.services');
        Route::get('/services/{service}', [ServicePanelController::class, 'show'])->name('dashboard.services.show');
        Route::get('/services/{service}/progress', [ServicePanelController::class, 'progress'])->middleware('throttle:60,1')->name('dashboard.services.progress');
        Route::post('/services/{service}/ai', [ServicePanelController::class, 'configureAi'])->middleware('throttle:20,1')->name('dashboard.services.ai');
        Route::post('/services/{service}/domain', [ServicePanelController::class, 'domain'])->middleware('throttle:20,1')->name('dashboard.services.domain');
        Route::post('/services/{service}/verify-domain', [ServicePanelController::class, 'verifyDomain'])->middleware('throttle:10,1')->name('dashboard.services.verify-domain');

        Route::get('/wallet', [DashboardController::class, 'wallet'])->name('dashboard.wallet');
        Route::post('/wallet/topup', [WalletTopupController::class, 'store'])->middleware('throttle:10,1')->name('dashboard.wallet.topup');

        Route::get('/profile', [CustomerProfileController::class, 'show'])->name('dashboard.profile');
        Route::put('/profile', [CustomerProfileController::class, 'update'])->middleware('throttle:20,1')->name('dashboard.profile.update');
        Route::get('/api-keys', [CustomerApiKeyController::class, 'index'])->name('dashboard.keys');
        Route::post('/api-keys', [CustomerApiKeyController::class, 'store'])->middleware('throttle:20,1')->name('dashboard.keys.store');
        Route::delete('/api-keys/{key}', [CustomerApiKeyController::class, 'destroy'])->whereNumber('key')->name('dashboard.keys.destroy');
        Route::put('/profile/billing', [CustomerProfileController::class, 'billing'])->middleware('throttle:20,1')->name('dashboard.profile.billing');
        Route::put('/profile/password', [CustomerProfileController::class, 'password'])->middleware('throttle:10,1')->name('dashboard.profile.password');

        Route::get('/subscriptions', [CustomerSubscriptionController::class, 'index'])->name('dashboard.subscriptions');
        Route::get('/subscriptions/{subscription}', [CustomerSubscriptionController::class, 'show'])->name('dashboard.subscriptions.show');
        Route::post('/subscriptions/{subscription}/cancel', [CustomerSubscriptionController::class, 'requestCancellation'])->middleware('throttle:10,1')->name('dashboard.subscriptions.cancel');
        Route::post('/subscriptions/{subscription}/cancel/{cancellation}/withdraw', [CustomerSubscriptionController::class, 'withdrawCancellation'])->middleware('throttle:10,1')->name('dashboard.subscriptions.cancel.withdraw');

        Route::get('/invoices', [DashboardController::class, 'invoices'])->name('dashboard.invoices');
        Route::get('/payments', [DashboardController::class, 'payments'])->name('dashboard.payments');
        Route::get('/invoices/{invoice}/pdf', [CustomerInvoiceController::class, 'download'])->name('dashboard.invoices.download');

        Route::get('/tickets', [CustomerTicketController::class, 'index'])->name('dashboard.tickets');
        Route::get('/tickets/new', [CustomerTicketController::class, 'create'])->name('dashboard.tickets.create');
        Route::post('/tickets', [CustomerTicketController::class, 'store'])->middleware('throttle:10,1')->name('dashboard.tickets.store');
        Route::get('/tickets/{ticket}', [CustomerTicketController::class, 'show'])->name('dashboard.tickets.show');
        Route::post('/tickets/{ticket}/reply', [CustomerTicketController::class, 'reply'])->middleware('throttle:20,1')->name('dashboard.tickets.reply');
        Route::post('/tickets/{ticket}/close', [CustomerTicketController::class, 'close'])->name('dashboard.tickets.close');
        Route::get('/tickets/{ticket}/attachments/{attachment}', [CustomerTicketController::class, 'attachment'])->name('dashboard.tickets.attachment');

        Route::get('/requests', [CustomerRequestController::class, 'index'])->name('dashboard.requests');
        Route::get('/requests/{serviceRequest}', [CustomerRequestController::class, 'show'])->name('dashboard.requests.show');
        Route::post('/requests/{serviceRequest}/accept', [CustomerRequestController::class, 'accept'])->name('dashboard.requests.accept');
        Route::post('/requests/{serviceRequest}/decline', [CustomerRequestController::class, 'decline'])->name('dashboard.requests.decline');

        Route::get('/automation', [CustomerAutomationController::class, 'index'])->name('dashboard.automation');
        Route::get('/automation/{lead}', [CustomerAutomationController::class, 'show'])->whereNumber('lead')->name('dashboard.automation.show');
        Route::post('/automation/{lead}/decision', [CustomerAutomationController::class, 'decide'])->whereNumber('lead')->middleware('throttle:10,1')->name('dashboard.automation.decide');

        Route::get('/projects', [CustomerProjectController::class, 'index'])->name('dashboard.projects');
        Route::get('/projects/{project}', [CustomerProjectController::class, 'show'])->name('dashboard.projects.show');
        Route::post('/projects/{project}/milestones/{milestone}', [CustomerProjectController::class, 'milestone'])->name('dashboard.projects.milestone');
        Route::post('/projects/{project}/messages', [CustomerProjectController::class, 'message'])->middleware('throttle:30,1')->name('dashboard.projects.message');
        Route::post('/projects/{project}/seo-tasks/{task}', [CustomerProjectController::class, 'seoTask'])->name('dashboard.projects.seo-task');
        Route::get('/projects/{project}/attachments/{attachment}', [CustomerProjectController::class, 'attachment'])->name('dashboard.projects.attachment');
        Route::get('/projects/{project}/reports/{report}', [CustomerProjectController::class, 'report'])->name('dashboard.projects.report');
    });
});

// ---------------------------------------------------------------------------------------------------------------
// Admin / staff area. Every route sits behind `auth` + `staff`; each action additionally enforces a permission.
// ---------------------------------------------------------------------------------------------------------------
// Staff portal: ONE sign-in for every staff role at /staff/{slug}/login. {slug} is compared with the value saved in
// the database on every request (ResolveStaffPortal), so a Super Admin can change it without touching routes or the
// route cache; any other value — including retired slugs — is a 404. The slug is not the only control: password,
// throttling, MFA, email ownership and authorization all apply independently.
Route::prefix('staff/{portal}')->where(['portal' => '[a-z0-9-]{1,64}'])->middleware('staff.portal')->name('staff.')->group(function () {
    Route::get('/login', [Staff\StaffAuthController::class, 'create'])->name('login');
    Route::post('/login', [Staff\StaffAuthController::class, 'store'])->middleware('throttle:20,1')->name('login.store');
    Route::get('/two-factor', [Staff\StaffAuthController::class, 'twoFactorForm'])->name('two-factor');
    Route::post('/two-factor', [Staff\StaffAuthController::class, 'twoFactorVerify'])->middleware('throttle:20,1')->name('two-factor.verify');
    Route::get('/verify-email', [Staff\StaffAuthController::class, 'emailForm'])->name('verify-email');
    Route::post('/verify-email', [Staff\StaffAuthController::class, 'emailVerify'])->middleware('throttle:10,1')->name('verify-email.verify');
    Route::post('/verify-email/resend', [Staff\StaffAuthController::class, 'emailResend'])->middleware('throttle:6,1')->name('verify-email.resend');
    Route::get('/forgot-password', [Staff\StaffPasswordController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [Staff\StaffPasswordController::class, 'email'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [Staff\StaffPasswordController::class, 'edit'])->name('password.reset');
    Route::post('/reset-password', [Staff\StaffPasswordController::class, 'update'])->middleware('throttle:5,1')->name('password.update');
    Route::get('/invitation/{token}', [Staff\StaffInvitationController::class, 'show'])->middleware('throttle:30,1')->name('invitation');
    Route::post('/invitation/{token}', [Staff\StaffInvitationController::class, 'accept'])->middleware('throttle:10,1')->name('invitation.accept');
});

Route::prefix('admin')->group(function () {
    Route::middleware(['staff', 'throttle:staff-area'])->group(function () {
        Route::post('/logout', [Staff\StaffAuthController::class, 'destroy'])->name('admin.logout');

        // My account (reachable even while a password change or MFA enrolment is still required)
        Route::get('/profile', [Admin\StaffProfileController::class, 'show'])->name('admin.profile');
        Route::put('/profile', [Admin\StaffProfileController::class, 'update'])->name('admin.profile.update');
        Route::put('/profile/password', [Admin\StaffProfileController::class, 'password'])->middleware('throttle:10,1')->name('admin.profile.password');
        Route::post('/profile/sessions', [Admin\StaffProfileController::class, 'signOutOthers'])->middleware('throttle:10,1')->name('admin.profile.sessions');
        Route::get('/confirm-access', [Admin\StaffProfileController::class, 'confirmForm'])->name('admin.confirm-access');
        Route::post('/confirm-access', [Admin\StaffProfileController::class, 'confirm'])->middleware('throttle:10,1')->name('admin.confirm-access.store');

        Route::get('/security', [Admin\SecurityController::class, 'show'])->name('admin.security');
        Route::post('/security/start', [Admin\SecurityController::class, 'start'])->name('admin.security.start');
        Route::post('/security/confirm', [Admin\SecurityController::class, 'confirm'])->middleware('throttle:10,1')->name('admin.security.confirm');
        Route::post('/security/recovery-codes', [Admin\SecurityController::class, 'regenerate'])->middleware('throttle:10,1')->name('admin.security.recovery-codes');
        Route::post('/security/disable', [Admin\SecurityController::class, 'disable'])->middleware('throttle:10,1')->name('admin.security.disable');
        Route::post('/staff/{user}/reset-mfa', [Admin\SecurityController::class, 'reset'])->middleware('staff.reauth')->name('admin.staff.reset-mfa');
        Route::get('/', Admin\DashboardController::class)->middleware('permission:dashboard.view')->name('admin.dashboard');

        // Orders & fulfilment
        Route::get('/orders', [Admin\OrderController::class, 'index'])->name('admin.orders.index');
        Route::get('/orders/{order}', [Admin\OrderController::class, 'show'])->name('admin.orders.show');
        Route::post('/orders/{order}/note', [Admin\OrderController::class, 'note'])->name('admin.orders.note');
        Route::post('/orders/{order}/assign', [Admin\OrderController::class, 'assign'])->name('admin.orders.assign');
        Route::post('/orders/{order}/cancel', [Admin\OrderController::class, 'cancel'])->name('admin.orders.cancel');
        Route::post('/orders/{order}/refund-request', [Admin\OrderController::class, 'refundRequest'])->name('admin.orders.refund-request');
        Route::post('/orders/{order}/refund-complete', [Admin\OrderController::class, 'refundComplete'])->name('admin.orders.refund-complete');
        Route::post('/orders/{order}/refund-reverse', [Admin\OrderController::class, 'reverseRefund'])->name('admin.orders.refund-reverse');
        Route::post('/services/{service}/assign-server', [Admin\DeploymentController::class, 'assign'])->name('admin.services.assign');
        Route::post('/services/{service}/domain', [Admin\DeploymentController::class, 'domain'])->name('admin.services.domain');
        Route::post('/services/{service}/setup', [Admin\DeploymentController::class, 'setup'])->name('admin.services.setup');
        Route::post('/services/{service}/deploy', [Admin\DeploymentController::class, 'deploy'])->middleware('throttle:20,1')->name('admin.services.deploy');
        Route::get('/deployments/{deployment}', [Admin\DeploymentController::class, 'show'])->name('admin.deployments.show');
        Route::get('/infrastructure', [Admin\InfrastructureController::class, 'index'])->name('admin.infrastructure');
        Route::post('/servers/{server}/request-suspension', [Admin\InfrastructureController::class, 'requestSuspension'])->middleware('throttle:20,1')->name('admin.servers.suspend');
        Route::post('/settings/test-notification', [Admin\InfrastructureController::class, 'testNotification'])->middleware('throttle:10,1')->name('admin.settings.test-notification');
        Route::post('/notifications/{log}/retry', [Admin\InfrastructureController::class, 'retryNotification'])->middleware('throttle:30,1')->name('admin.notifications.retry');
        Route::post('/provisioning-mode', [Admin\DeploymentController::class, 'mode'])->name('admin.provisioning.mode');

        // Catalogue
        Route::get('/plans', [Admin\PlanController::class, 'index'])->name('admin.plans.index');
        Route::get('/plans/create', [Admin\PlanController::class, 'create'])->name('admin.plans.create');
        Route::post('/plans', [Admin\PlanController::class, 'store'])->name('admin.plans.store');
        Route::get('/plans/{plan}/edit', [Admin\PlanController::class, 'edit'])->name('admin.plans.edit');
        Route::put('/plans/{plan}', [Admin\PlanController::class, 'update'])->name('admin.plans.update');
        Route::delete('/plans/{plan}', [Admin\PlanController::class, 'destroy'])->name('admin.plans.destroy');

        // Wallet & invoices
        Route::get('/billing', [Admin\BillingController::class, 'index'])->name('admin.billing.index');
        Route::get('/billing/subscriptions/{subscription}', [Admin\BillingController::class, 'show'])->name('admin.billing.show');
        Route::post('/billing/subscriptions/{subscription}/action', [Admin\BillingController::class, 'action'])->middleware('throttle:60,1')->name('admin.billing.action');
        Route::post('/billing/reminders/{reminder}/retry', [Admin\BillingController::class, 'retryReminder'])->middleware('throttle:30,1')->name('admin.billing.reminders.retry');
        Route::post('/billing/cancellations/{cancellation}', [Admin\BillingController::class, 'reviewCancellation'])->name('admin.billing.cancellations.review');
        Route::post('/billing/subscriptions/{subscription}/retry', [Admin\BillingController::class, 'retryRenewal'])->middleware('throttle:30,1')->name('admin.billing.retry');
        Route::post('/billing/subscriptions/{subscription}/addons', [Admin\BillingController::class, 'addAddon'])->middleware('staff.reauth')->name('admin.billing.addons.store');
        Route::delete('/billing/subscriptions/{subscription}/addons/{addon}', [Admin\BillingController::class, 'removeAddon'])->name('admin.billing.addons.destroy');
        Route::get('/billing-ops', [Admin\BillingOpsController::class, 'index'])->name('admin.billing-ops');
        Route::get('/exports', [Admin\ExportController::class, 'index'])->name('admin.exports');
        Route::get('/exports/{type}', [Admin\ExportController::class, 'download'])->middleware('throttle:20,1')->where('type', '[a-z_]+')->name('admin.exports.download');
        Route::post('/billing-ops/reconcile', [Admin\BillingOpsController::class, 'reconcile'])->middleware('throttle:6,1')->name('admin.billing-ops.reconcile');
        Route::get('/wallet', [Admin\WalletController::class, 'index'])->name('admin.wallet.index');
        Route::get('/wallet/customers', [Admin\WalletController::class, 'customers'])->middleware('throttle:120,1')->name('admin.wallet.customers');
        Route::post('/wallet/topups/{topup}/approve', [Admin\WalletController::class, 'approve'])->name('admin.wallet.approve');
        Route::post('/wallet/topups/{topup}/reject', [Admin\WalletController::class, 'reject'])->name('admin.wallet.reject');
        Route::post('/wallet/adjust', [Admin\WalletController::class, 'adjust'])->name('admin.wallet.adjust');
        Route::get('/invoices/{invoice}/pdf', [Admin\InvoiceController::class, 'download'])->name('admin.invoices.download');

        // Support
        Route::get('/tickets', [Admin\TicketController::class, 'index'])->name('admin.tickets.index');
        Route::get('/tickets/{ticket}', [Admin\TicketController::class, 'show'])->name('admin.tickets.show');
        Route::post('/tickets/{ticket}/reply', [Admin\TicketController::class, 'reply'])->name('admin.tickets.reply');
        Route::post('/tickets/{ticket}/assign', [Admin\TicketController::class, 'assign'])->name('admin.tickets.assign');
        Route::post('/tickets/{ticket}/status', [Admin\TicketController::class, 'status'])->name('admin.tickets.status');
        Route::post('/tickets/{ticket}/priority', [Admin\TicketController::class, 'priority'])->name('admin.tickets.priority');
        Route::post('/tickets/{ticket}/transfer', [Admin\TicketController::class, 'transfer'])->name('admin.tickets.transfer');
        Route::post('/tickets/{ticket}/escalate', [Admin\TicketController::class, 'escalate'])->middleware('throttle:30,1')->name('admin.tickets.escalate');
        Route::get('/tickets/{ticket}/attachments/{attachment}', [Admin\TicketController::class, 'attachment'])->name('admin.tickets.attachment');

        // Staff, roles, settings
        Route::get('/staff', [Admin\StaffController::class, 'index'])->name('admin.staff.index');
        Route::post('/staff', [Admin\StaffController::class, 'store'])->name('admin.staff.store');
        Route::put('/staff/{user}', [Admin\StaffController::class, 'update'])->middleware('staff.reauth')->name('admin.staff.update');
        Route::get('/staff/{user}', [Admin\StaffController::class, 'show'])->whereNumber('user')->name('admin.staff.show');
        Route::post('/staff/invitations', [Admin\StaffController::class, 'invite'])->middleware('throttle:20,1')->name('admin.staff.invite');
        Route::post('/staff/invitations/{invitation}/revoke', [Admin\StaffController::class, 'revokeInvitation'])->name('admin.staff.invitations.revoke');
        Route::post('/staff/{user}/suspend', [Admin\StaffController::class, 'suspend'])->middleware('staff.reauth')->name('admin.staff.suspend');
        Route::post('/staff/{user}/reactivate', [Admin\StaffController::class, 'reactivate'])->middleware('staff.reauth')->name('admin.staff.reactivate');
        Route::post('/staff/{user}/reset-access', [Admin\StaffController::class, 'resetAccess'])->middleware('staff.reauth')->name('admin.staff.reset-access');
        Route::post('/staff/{user}/revoke-sessions', [Admin\StaffController::class, 'revokeSessions'])->middleware('staff.reauth')->name('admin.staff.revoke-sessions');
        Route::post('/roles', [Admin\StaffController::class, 'saveRole'])->name('admin.roles.create');
        Route::post('/roles/{role}', [Admin\StaffController::class, 'saveRole'])->name('admin.roles.save');
        Route::delete('/roles/{role}', [Admin\StaffController::class, 'destroyRole'])->name('admin.roles.destroy');
        Route::get('/settings', [Admin\SettingsController::class, 'edit'])->name('admin.settings.edit');
        Route::put('/settings', [Admin\SettingsController::class, 'update'])->name('admin.settings.update');
        Route::post('/settings/test-smtp', [Admin\SettingsController::class, 'testSmtp'])->middleware('throttle:10,1')->name('admin.settings.test-smtp');
        Route::get('/settings/staff-portal', [Admin\StaffPortalSettingsController::class, 'edit'])->name('admin.settings.staff-portal');
        Route::get('/settings/staff-portal/check', [Admin\StaffPortalSettingsController::class, 'check'])->middleware('throttle:60,1')->name('admin.settings.staff-portal.check');
        Route::put('/settings/staff-portal', [Admin\StaffPortalSettingsController::class, 'update'])->middleware('throttle:10,1')->name('admin.settings.staff-portal.update');

        // User Manager: one place for Customers + Staff (Support / Sales teams), email change, impersonation.
        Route::get('/users', [Admin\UserManagerController::class, 'index'])->name('admin.users.index');
        Route::post('/users', [Admin\UserManagerController::class, 'store'])->middleware('throttle:30,1')->name('admin.users.store');
        Route::get('/users/{user}', [Admin\UserManagerController::class, 'show'])->whereNumber('user')->name('admin.users.show');
        Route::post('/users/{user}/email', [Admin\UserManagerController::class, 'updateEmail'])->whereNumber('user')->middleware('staff.reauth')->name('admin.users.email');
        Route::post('/users/{user}/impersonate', [Admin\UserManagerController::class, 'impersonate'])->whereNumber('user')->middleware(['staff.reauth', 'throttle:10,1'])->name('admin.users.impersonate');
        Route::post('/impersonation/exit', [Admin\UserManagerController::class, 'exitImpersonation'])->name('admin.impersonation.exit');

        // Business Automation leads & quotations (sales workflow; permission + ownership checked per action)
        Route::get('/leads', [Admin\AutomationLeadController::class, 'index'])->name('admin.leads.index');
        Route::get('/leads/{lead}', [Admin\AutomationLeadController::class, 'show'])->whereNumber('lead')->name('admin.leads.show');
        Route::post('/leads/{lead}/status', [Admin\AutomationLeadController::class, 'status'])->whereNumber('lead')->name('admin.leads.status');
        Route::post('/leads/{lead}/note', [Admin\AutomationLeadController::class, 'note'])->whereNumber('lead')->name('admin.leads.note');
        Route::post('/leads/{lead}/request-info', [Admin\AutomationLeadController::class, 'requestInfo'])->whereNumber('lead')->middleware('throttle:20,1')->name('admin.leads.request-info');
        Route::post('/leads/{lead}/quote', [Admin\AutomationLeadController::class, 'quote'])->whereNumber('lead')->name('admin.leads.quote');
        Route::post('/leads/{lead}/assign', [Admin\AutomationLeadController::class, 'assign'])->whereNumber('lead')->name('admin.leads.assign');
        Route::post('/leads/{lead}/follow-up', [Admin\AutomationLeadController::class, 'followUp'])->whereNumber('lead')->name('admin.leads.follow-up');
        Route::post('/leads/{lead}/link-account', [Admin\AutomationLeadController::class, 'linkAccount'])->whereNumber('lead')->name('admin.leads.link-account');
        Route::post('/leads/{lead}/convert', [Admin\AutomationLeadController::class, 'convert'])->whereNumber('lead')->name('admin.leads.convert');

        // Projects, quotes, SEO
        Route::get('/requests/{serviceRequest}', [Admin\ServiceRequestController::class, 'show'])->name('admin.requests.show');
        Route::post('/requests/{serviceRequest}/quote', [Admin\ServiceRequestController::class, 'quote'])->name('admin.requests.quote');
        Route::get('/requests/{serviceRequest}/attachments/{attachment}', [Admin\ServiceRequestController::class, 'attachment'])->name('admin.requests.attachment');
        Route::get('/projects', [Admin\ProjectController::class, 'index'])->name('admin.projects.index');
        Route::get('/projects/{project}', [Admin\ProjectController::class, 'show'])->name('admin.projects.show');
        Route::put('/projects/{project}', [Admin\ProjectController::class, 'update'])->name('admin.projects.update');
        Route::post('/projects/{project}/milestones', [Admin\ProjectController::class, 'milestoneStore'])->name('admin.projects.milestones.store');
        Route::put('/projects/{project}/milestones/{milestone}', [Admin\ProjectController::class, 'milestoneUpdate'])->name('admin.projects.milestones.update');
        Route::post('/projects/{project}/tasks', [Admin\ProjectController::class, 'taskStore'])->name('admin.projects.tasks.store');
        Route::put('/projects/{project}/tasks/{task}', [Admin\ProjectController::class, 'taskUpdate'])->name('admin.projects.tasks.update');
        Route::post('/projects/{project}/messages', [Admin\ProjectController::class, 'message'])->name('admin.projects.message');
        Route::get('/projects/{project}/attachments/{attachment}', [Admin\ProjectController::class, 'attachment'])->name('admin.projects.attachment');
        Route::get('/seo', [Admin\SeoController::class, 'index'])->name('admin.seo.index');
        Route::get('/seo/{project}', [Admin\SeoController::class, 'show'])->name('admin.seo.show');
        Route::post('/seo/{project}/audits', [Admin\SeoController::class, 'audit'])->name('admin.seo.audit');
        Route::post('/seo/{project}/tasks', [Admin\SeoController::class, 'task'])->name('admin.seo.task');
        Route::put('/seo/{project}/tasks/{task}', [Admin\SeoController::class, 'taskStatus'])->name('admin.seo.task-status');
        Route::post('/seo/{project}/reports', [Admin\SeoController::class, 'report'])->name('admin.seo.report');
        Route::post('/seo/{project}/reports/{report}/publish', [Admin\SeoController::class, 'publish'])->name('admin.seo.publish');

        // Generic config-driven resources (customers, categories, add-ons, servers, logs, docs, …)
        Route::get('/r/{resource}', [Admin\ResourceController::class, 'index'])->name('admin.resource.index');
        Route::get('/r/{resource}/create', [Admin\ResourceController::class, 'create'])->name('admin.resource.create');
        Route::post('/r/{resource}', [Admin\ResourceController::class, 'store'])->name('admin.resource.store');
        Route::get('/r/{resource}/{id}/edit', [Admin\ResourceController::class, 'edit'])->whereNumber('id')->name('admin.resource.edit');
        Route::put('/r/{resource}/{id}', [Admin\ResourceController::class, 'update'])->whereNumber('id')->name('admin.resource.update');
        Route::delete('/r/{resource}/{id}', [Admin\ResourceController::class, 'destroy'])->whereNumber('id')->name('admin.resource.destroy');
    });
});
