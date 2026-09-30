<div class="row g-3">
    <div class="col-md-6"><label class="form-label" for="name">Name / ឈ្មោះ *</label><input class="form-control" id="name" name="name" value="{{ old('name', $customer->name ?? '') }}" required></div>
    <div class="col-md-6"><label class="form-label" for="source">Source *</label><select class="form-select" id="source" name="source" required>@foreach($sources as $source)<option value="{{ $source->value }}" @selected(old('source', isset($customer) ? $customer->source->value : 'manual') === $source->value)>{{ $source->label() }}</option>@endforeach</select></div>
    <div class="col-md-6"><label class="form-label" for="phone">Phone / លេខទូរស័ព្ទ</label><input class="form-control" id="phone" name="phone" value="{{ old('phone', $customer->phone ?? '') }}"></div>
    <div class="col-md-6"><label class="form-label" for="phone_secondary">Secondary phone / លេខទូរស័ព្ទទី២</label><input class="form-control" id="phone_secondary" name="phone_secondary" value="{{ old('phone_secondary', $customer->phone_secondary ?? '') }}"></div>
    <div class="col-md-6"><label class="form-label" for="facebook_name">Facebook name</label><input class="form-control" id="facebook_name" name="facebook_name" value="{{ old('facebook_name', $customer->facebook_name ?? '') }}"></div>
    <div class="col-md-6"><label class="form-label" for="facebook_profile_url">Facebook profile URL</label><input class="form-control" id="facebook_profile_url" type="url" name="facebook_profile_url" value="{{ old('facebook_profile_url', $customer->facebook_profile_url ?? '') }}"></div>
    <div class="col-md-6"><label class="form-label" for="email">Email</label><input class="form-control" id="email" type="email" name="email" value="{{ old('email', $customer->email ?? '') }}"></div>
    <div class="col-md-6"><label class="form-label" for="province">Province / ខេត្ត-រាជធានី</label><input class="form-control" id="province" name="province" value="{{ old('province', $customer->province ?? '') }}"></div>
    <div class="col-md-6"><label class="form-label" for="district">District / ស្រុក-ខណ្ឌ</label><input class="form-control" id="district" name="district" value="{{ old('district', $customer->district ?? '') }}"></div>
    <div class="col-md-6"><label class="form-label" for="commune">Commune / ឃុំ-សង្កាត់</label><input class="form-control" id="commune" name="commune" value="{{ old('commune', $customer->commune ?? '') }}"></div>
    <div class="col-12"><label class="form-label" for="address">Address / អាសយដ្ឋាន</label><textarea class="form-control" id="address" name="address" rows="2">{{ old('address', $customer->address ?? '') }}</textarea></div>
    <div class="col-12"><label class="form-label" for="notes">Notes</label><textarea class="form-control" id="notes" name="notes" rows="3">{{ old('notes', $customer->notes ?? '') }}</textarea></div>
</div>

