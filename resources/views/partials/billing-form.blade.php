{{-- Saved billing details (Profile → Billing details). $user, optional $return = 'checkout'. --}}
@php($countries = \App\Http\Controllers\CustomerProfileController::COUNTRIES)
<form method="POST" action="{{ route('dashboard.profile.billing') }}" class="billing-form" data-loading-form>
    @csrf @method('PUT')
    @isset($return)<input type="hidden" name="return" value="{{ $return }}">@endisset
    <div class="form-grid">
        <label>Company name <small class="muted">(optional)</small>
            <input name="billing_company" value="{{ old('billing_company', $user->billing_company) }}" maxlength="150" autocomplete="organization">
        </label>
        <label>GSTIN <small class="muted">(optional, for a GST invoice)</small>
            <input name="gstin" value="{{ old('gstin', $user->gstin) }}" maxlength="15" placeholder="27ABCDE1234F1Z5" autocomplete="off" style="text-transform:uppercase">
            @error('gstin', 'billing')<span class="field-error">{{ $message }}</span>@enderror
        </label>
        <label class="span-2">Street address
            <input name="billing_address" value="{{ old('billing_address', $user->billing_address) }}" maxlength="500" required autocomplete="street-address" placeholder="Flat / building, street, area">
            @error('billing_address', 'billing')<span class="field-error">{{ $message }}</span>@enderror
        </label>
        <label>City
            <input name="billing_city" value="{{ old('billing_city', $user->billing_city) }}" maxlength="80" required autocomplete="address-level2">
            @error('billing_city', 'billing')<span class="field-error">{{ $message }}</span>@enderror
        </label>
        <label>State
            <input name="billing_state" value="{{ old('billing_state', $user->billing_state) }}" maxlength="80" required autocomplete="address-level1" placeholder="e.g. Maharashtra">
            @error('billing_state', 'billing')<span class="field-error">{{ $message }}</span>@enderror
        </label>
        <label>PIN / ZIP code
            <input name="billing_postcode" value="{{ old('billing_postcode', $user->billing_postcode) }}" maxlength="12" required autocomplete="postal-code" inputmode="numeric">
            @error('billing_postcode', 'billing')<span class="field-error">{{ $message }}</span>@enderror
        </label>
        <label>Country
            <select name="billing_country" required autocomplete="country">
                @foreach ($countries as $code => $name)<option value="{{ $code }}" @selected(old('billing_country', $user->billing_country ?? 'IN') === $code)>{{ $name }}</option>@endforeach
            </select>
        </label>
    </div>
    <div class="btn-row"><button class="button-primary" type="submit">Save billing details</button></div>
</form>
