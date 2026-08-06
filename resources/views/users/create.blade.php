<x-layouts.app title="ユーザー登録">
    <x-slot:header>
        <h1 class="text-lg font-bold text-slate-800">ユーザー登録</h1>
    </x-slot:header>

    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="bg-white rounded-xl shadow-sm px-6 py-8">
            <form method="POST" action="{{ route('users.store') }}" class="space-y-5">
                @include('users.partials.form', ['user' => null, 'submitLabel' => '登録する'])
            </form>
        </div>
    </div>
</x-layouts.app>
