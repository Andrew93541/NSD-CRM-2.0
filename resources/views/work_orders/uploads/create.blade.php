@extends('layouts.app')

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-amber-600 dark:text-amber-400">
                <span>JSW Service Order Automation</span>
                <span>•</span>
                <span>Phase G & H</span>
            </div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white mt-1">Upload Client Work Order (PDF)</h1>
            <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">
                Upload a received JSW Steel Service Order PDF (up to 10 MB). Text is extracted 100% locally on the server, verified against arithmetic totals, and prepared for review before creating the Work Order and Site.
            </p>
        </div>
        <a href="{{ route('work_orders.index') }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg border border-slate-300 dark:border-slate-700 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800">
            ← Back to Work Orders
        </a>
    </div>

    @if ($errors->any())
        <div class="rounded-xl border border-red-300 dark:border-red-800 bg-red-50 dark:bg-red-950/40 p-4 text-sm text-red-700 dark:text-red-300">
            <p class="font-semibold mb-1">Please fix the following errors:</p>
            <ul class="list-disc list-inside space-y-0.5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm p-6">
        <form action="{{ route('work_orders.uploads.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
            @csrf

            <div>
                <label for="pdf_file" class="block text-sm font-semibold text-slate-800 dark:text-slate-200 mb-2">
                    Select Work Order PDF (Max 10 MB)
                </label>
                <input
                    type="file"
                    id="pdf_file"
                    name="pdf_file"
                    accept="application/pdf,.pdf"
                    required
                    class="block w-full text-sm text-slate-700 dark:text-slate-200 file:mr-4 file:py-2.5 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-amber-500 file:text-slate-950 hover:file:bg-amber-400 border border-slate-300 dark:border-slate-700 rounded-lg p-2 bg-slate-50 dark:bg-slate-800/60"
                >
                <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                    Stored in private vault storage. Bank account numbers in vendor blocks are automatically masked on screen and never logged.
                </p>
            </div>

            <div class="flex items-start gap-3 p-4 rounded-lg bg-amber-50/70 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800/60">
                <input
                    type="checkbox"
                    id="is_draft_po"
                    name="is_draft_po"
                    value="1"
                    class="mt-1 h-4 w-4 rounded border-slate-300 text-amber-600 focus:ring-amber-500"
                >
                <div>
                    <label for="is_draft_po" class="text-sm font-semibold text-slate-900 dark:text-white">
                        Mark as Draft PO (if watermarked or preliminary)
                    </label>
                    <p class="text-xs text-slate-600 dark:text-slate-400 mt-0.5">
                        Note: Our parser also detects the JSW <span class="font-mono">DRAFT PO</span> watermark automatically. Draft POs allow site setup and field measurements, while locking RA Bill generation until the final PO revision is uploaded.
                    </p>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-2">
                <a href="{{ route('work_orders.create') }}" class="px-4 py-2.5 rounded-lg border border-slate-300 dark:border-slate-700 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800">
                    Manual Entry Instead
                </a>
                <button type="submit" class="px-5 py-2.5 rounded-lg bg-amber-500 hover:bg-amber-400 text-slate-950 font-semibold text-sm shadow-sm">
                    Upload & Extract Draft →
                </button>
            </div>
        </form>
    </div>

    @if ($recentUploads->isNotEmpty())
        <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-800">
                <h2 class="text-base font-bold text-slate-900 dark:text-white">Recent Work Order Uploads</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800 text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-800/60 text-xs uppercase text-slate-500 dark:text-slate-400">
                        <tr>
                            <th class="px-4 py-3 text-left">File</th>
                            <th class="px-4 py-3 text-left">PO No.</th>
                            <th class="px-4 py-3 text-left">Status</th>
                            <th class="px-4 py-3 text-left">Uploaded By</th>
                            <th class="px-4 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                        @foreach ($recentUploads as $item)
                            @php
                                $p = $item->activePayload();
                            @endphp
                            <tr>
                                <td class="px-4 py-3 font-medium text-slate-900 dark:text-white">{{ $item->original_filename }}</td>
                                <td class="px-4 py-3 font-mono text-xs">{{ $p['header']['po_number'] ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    <span class="px-2.5 py-1 rounded-full text-xs font-semibold {{ $item->status === 'confirmed' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300' : ($item->status === 'unreadable' ? 'bg-red-100 text-red-800 dark:bg-red-950/60 dark:text-red-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300') }}">
                                        {{ strtoupper($item->status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-slate-600 dark:text-slate-400">{{ $item->uploader?->name ?? 'System' }}</td>
                                <td class="px-4 py-3 text-right">
                                    @if ($item->workOrder)
                                        <a href="{{ route('work_orders.show', $item->workOrder) }}" class="text-amber-600 dark:text-amber-400 font-semibold hover:underline">View Order</a>
                                    @else
                                        <a href="{{ route('work_orders.uploads.review', $item) }}" class="text-amber-600 dark:text-amber-400 font-semibold hover:underline">Review Draft →</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
@endsection

