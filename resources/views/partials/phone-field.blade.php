{{-- Phone: dialling-code select (default +91) + national number. Stored as E.164; no SMS verification. --}}
@php
    [$defCode, $defNational] = \App\Support\PhoneNumber::split($value ?? null);
    $code = old('phone_country', $defCode);
    $national = old('phone', $defNational);
    $idPrefix = $idPrefix ?? 'phone';
@endphp
<label for="{{ $idPrefix }}-phone">Phone number</label>
<div class="phone-field">
    <select id="{{ $idPrefix }}-phone-country" name="phone_country" aria-label="Country calling code" autocomplete="tel-country-code">
        @foreach (config('phone.countries') as $dial => [$country])
            <option value="{{ $dial }}" @selected($code === $dial)>{{ $dial }} {{ $country }}</option>
        @endforeach
    </select>
    <input id="{{ $idPrefix }}-phone" type="tel" name="phone" value="{{ $national }}" inputmode="tel" autocomplete="tel-national" required maxlength="24" placeholder="98765 43210"
        aria-describedby="{{ $idPrefix }}-phone-hint @error('phone') err-phone @enderror" @error('phone') aria-invalid="true" @enderror>
</div>
<p class="field-hint" id="{{ $idPrefix }}-phone-hint">Used for service and billing contact only. We do not send SMS codes.</p>
@error('phone')<p class="field-error" id="err-phone">{{ $message }}</p>@enderror
@error('phone_country')<p class="field-error">{{ $message }}</p>@enderror
