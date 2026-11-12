@extends('layouts.app')

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-amber-600 dark:text-amber-400">
                <a href="{{ route('admin.roles.index') }}" class="hover:underline">{{ __('Roles') }}</a>
                <span>/</span>
                <span>{{ __('Create Custom Role') }}</span>
            </div>
            <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white mt-1">{{ __('Create Custom Company Role') }}</h1>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                {{ __('Define a custom role (e.g. Accountant, HR Manager, Site Engineer, Procurement Officer) and select its allowed CRM actions.') }}
            </p>
        </div>

        <a href="{{ route('admin.roles.index') }}" class="px-3.5 py-2 rounded-xl border border-slate-300 dark:border-slate-700 text-xs font-bold text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition self-start">
            ← {{ __('Back to Roles') }}
        </a>
    </div>

    <form method="POST" action="{{ route('admin.roles.store') }}" class="space-y-6">
        @csrf

        <div class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
            <h2 class="text-sm font-extrabold uppercase tracking-wider text-slate-900 dark:text-white">{{ __('1. Role Name & Description') }}</h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Role Display Name') }} <span class="text-rose-500">*</span></label>
                    <input type="text" id="role-name-input" name="name" value="{{ old('name') }}" required placeholder="e.g. Accountant, HR Manager, Site Engineer..."
                        class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-950 px-3.5 py-2.5 text-xs text-slate-900 dark:text-white focus:border-amber-500 outline-none">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Role Slug (optional auto-generated)') }}</label>
                    <input type="text" id="role-slug-input" name="slug" value="{{ old('slug') }}" placeholder="e.g. accountant"
                        class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-950 px-3.5 py-2.5 text-xs font-mono text-slate-900 dark:text-white focus:border-amber-500 outline-none">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Description') }}</label>
                <input type="text" id="role-desc-input" name="description" value="{{ old('description') }}" placeholder="Brief summary of responsibilities and module access..."
                    class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-950 px-3.5 py-2.5 text-xs text-slate-900 dark:text-white focus:border-amber-500 outline-none">
            </div>

            <!-- Quick Role Presets -->
            <div class="pt-2">
                <div class="text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2">{{ __('Quick-Fill Recommended Role Templates:') }}</div>
                <div class="flex flex-wrap gap-2">
                    @foreach($templates as $tplSlug => $tpl)
                        <button type="button"
                            onclick="applyRoleTemplate(@js($tpl['name']), @js($tplSlug), @js($tpl['description']), @js($tpl['permissions']))"
                            class="px-3 py-1.5 rounded-xl border border-amber-500/30 bg-amber-500/10 hover:bg-amber-500/20 text-amber-700 dark:text-amber-300 text-xs font-bold transition">
                            + {{ $tpl['name'] }}
                        </button>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- 2. Module Permissions -->
        <div class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
            <h2 class="text-sm font-extrabold uppercase tracking-wider text-slate-900 dark:text-white">{{ __('2. Module-Level Permissions') }}</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach($modules as $moduleKey => $moduleDef)
                    @if($moduleKey !== 'users')
                        <div class="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-950/40 space-y-2.5">
                            <div>
                                <h3 class="text-xs font-extrabold uppercase tracking-wider text-slate-900 dark:text-white">{{ $moduleDef['label'] }}</h3>
                                <p class="text-[11px] text-slate-500 dark:text-slate-400">{{ $moduleDef['description'] }}</p>
                            </div>
                            <div class="space-y-1.5 pt-1">
                                @foreach($moduleDef['permissions'] as $permKey => $permLabel)
                                    <label class="flex items-center gap-2.5 text-xs cursor-pointer">
                                        <input type="checkbox" name="permissions[]" value="{{ $permKey }}"
                                            class="role-perm-checkbox rounded text-amber-500 focus:ring-amber-500"
                                            {{ in_array($permKey, (array) old('permissions', ['dashboard.view']), true) ? 'checked' : '' }}>
                                        <span class="font-semibold text-slate-800 dark:text-slate-200">{{ $permLabel }}</span>
                                        <span class="text-[10px] font-mono text-slate-400">({{ $permKey }})</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>

        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('admin.roles.index') }}" class="px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 text-xs font-bold text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition">
                {{ __('Cancel') }}
            </a>
            <button type="submit" class="px-5 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 text-xs font-extrabold shadow-lg shadow-amber-500/20 transition">
                {{ __('Create Custom Role') }}
            </button>
        </div>
    </form>
</div>

<script>
function applyRoleTemplate(name, slug, description, permissions) {
    document.getElementById('role-name-input').value = name;
    document.getElementById('role-slug-input').value = slug;
    document.getElementById('role-desc-input').value = description;
    var boxes = document.querySelectorAll('.role-perm-checkbox');
    boxes.forEach(function(cb) {
        cb.checked = permissions.indexOf(cb.value) !== -1;
    });
}
</script>
@endsection

