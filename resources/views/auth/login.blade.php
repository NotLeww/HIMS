<x-guest-layout :title="($supplierPortal ?? false) ? 'Supplier Sign in' : 'Staff Sign in'" :portal="($supplierPortal ?? false) ? 'supplier' : 'staff'">
    <x-auth.login-panel
        :action="($supplierPortal ?? false) ? route('supplier.login') : route('login')"
        :forgot-password-url="route('password.request')"
        :heading="($supplierPortal ?? false) ? 'Supplier Portal Sign in' : 'Staff Sign in'"
        :description="($supplierPortal ?? false) ? 'Access orders, shipments, bids, invoices, and performance for your approved supplier organization.' : 'Access the HIMS modules authorized for your hospital role.'"
        :submit-label="($supplierPortal ?? false) ? 'Sign in as Supplier' : 'Sign in as Staff'"
        :login-restriction="$loginRestriction"
    />
</x-guest-layout>
