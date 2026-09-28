<div class="row g-3">
    <div class="col-md-8"><label class="form-label" for="name">Name *</label><input class="form-control" id="name" name="name" value="{{ old('name', $product->name ?? '') }}" required></div>
    <div class="col-md-4"><label class="form-label" for="sku">SKU</label><input class="form-control" id="sku" name="sku" value="{{ old('sku', $product->sku ?? '') }}"></div>
    <div class="col-md-4"><label class="form-label" for="category">Category</label><input class="form-control" id="category" name="category" value="{{ old('category', $product->category ?? '') }}"></div>
    <div class="col-md-4"><label class="form-label" for="base_price">Base price *</label><div class="input-group"><span class="input-group-text">$</span><input class="form-control" id="base_price" type="number" step="0.01" min="0" name="base_price" value="{{ old('base_price', $product->base_price ?? '0.00') }}" required></div></div>
    <div class="col-md-4"><label class="form-label" for="low_stock_threshold">Low-stock threshold *</label><input class="form-control" id="low_stock_threshold" type="number" min="0" name="low_stock_threshold" value="{{ old('low_stock_threshold', $product->low_stock_threshold ?? 5) }}" required></div>
    @unless(isset($product))<div class="col-md-4"><label class="form-label" for="initial_stock">Initial stock</label><input class="form-control" id="initial_stock" type="number" min="0" name="initial_stock" value="{{ old('initial_stock', 0) }}"><div class="form-text">Use zero if this product will have variants.</div></div>@endunless
    <div class="col-12"><label class="form-label" for="description">Description</label><textarea class="form-control" id="description" name="description" rows="3">{{ old('description', $product->description ?? '') }}</textarea></div>
    <div class="col-12"><input type="hidden" name="active" value="0"><div class="form-check"><input class="form-check-input" id="active" type="checkbox" name="active" value="1" @checked((bool) old('active', $product->active ?? true))><label class="form-check-label" for="active">Active and available for new orders</label></div></div>
</div>

