@extends('layouts.app')

@section('title', 'Categories')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Categories</h2>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#categoryModal" onclick="resetCategoryForm()">
            <i class="fas fa-plus"></i> Add Category
        </button>
    </div>

    <x-alert />

    <div class="card">
        <div class="card-body">
            <x-data-table :headers="['Code', 'Description', 'Status']" :rows="$categories">
                @foreach ($categories as $category)
                    <tr>
                        <td><strong>{{ $category->code }}</strong></td>
                        <td>{{ $category->description }}</td>
                        <td>
                            @if ($category->is_active)
                                <x-status-badge status="Active" />
                            @else
                                <x-status-badge status="Inactive" />
                            @endif
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm" role="group">
                                <button type="button" class="btn btn-warning edit-category-btn" data-bs-toggle="modal" data-bs-target="#categoryModal" data-category="{{ $category->toJson() }}">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="POST" action="{{ route('settings.category-delete', $category) }}" style="display:inline;">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure?')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-data-table>
        </div>
    </div>
</div>

<!-- Category Modal -->
<div class="modal fade" id="categoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="categoryModalTitle">Add New Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="categoryForm" method="POST" action="{{ route('settings.category-store') }}" data-store-url="{{ route('settings.category-store') }}" data-update-url-pattern="/settings/categories/">
                @csrf
                <input type="hidden" id="categoryId" name="category_id">
                <input type="hidden" id="categoryFormMethod" name="_method" value="POST">
                <div class="modal-body">
                    <x-form-group
                        label="Category Code"
                        name="code"
                        type="text"
                        required
                    />

                    <x-form-group
                        label="Description"
                        name="description"
                        type="text"
                        required
                    />

                    <x-form-group
                        label="Status"
                        name="is_active"
                        type="select"
                    >
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </x-form-group>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const categoryForm = document.getElementById('categoryForm');
const categoryStoreUrl = categoryForm.dataset.storeUrl;
const categoryUpdateUrlPattern = categoryForm.dataset.updateUrlPattern;

document.querySelectorAll('.edit-category-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const categoryData = JSON.parse(this.dataset.category);
        editCategory(categoryData);
    });
});

function resetCategoryForm() {
    document.getElementById('categoryForm').reset();
    document.getElementById('categoryId').value = '';
    document.getElementById('categoryFormMethod').value = 'POST';
    document.getElementById('categoryModalTitle').textContent = 'Add New Category';
    categoryForm.action = categoryStoreUrl;

    document.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
}

function editCategory(category) {
    document.getElementById('categoryId').value = category.id;
    document.getElementById('categoryFormMethod').value = 'PUT';
    document.getElementById('categoryModalTitle').textContent = 'Edit Category: ' + category.code;
    categoryForm.action = categoryUpdateUrlPattern + category.id;

    document.querySelector('input[name="code"]').value = category.code;
    document.querySelector('input[name="description"]').value = category.description;
    document.querySelector('select[name="is_active"]').value = category.is_active ? '1' : '0';
}
</script>
@endsection
