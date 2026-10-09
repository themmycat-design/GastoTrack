<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Staff | GastoTrack</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="gt-owner-page min-h-screen bg-[#F4F8F7] font-sans text-[#0A2E2A] antialiased" style="font-family: Inter, ui-sans-serif, system-ui, sans-serif">
    <div class="min-h-screen lg:flex">
        @include('layouts.owner-navigation')

        <main class="min-w-0 flex-1">
            @include('layouts.owner-topbar')

            <div class="mx-auto max-w-[1600px] space-y-6 px-5 py-6 sm:px-8 sm:py-8 lg:px-10">
        <section class="gt-enter flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
            <div>
                <p class="text-sm font-semibold text-[#00A87E]">Your team</p>
                <h2 class="mt-1 text-2xl font-extrabold tracking-tight text-[#0A2E2A] sm:text-3xl">Staff</h2>
                <p class="mt-2 text-sm leading-6 text-slate-500">Manage team accounts and access to your business workspace.</p>
            </div>
            <button onclick="openAddModal()" class="gt-focus inline-flex items-center justify-center gap-2 rounded-xl bg-[#00A87E] px-5 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-[#08745D]">
                + Add User
            </button>
        </section>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div class="gt-card gt-lift gt-enter gt-enter-1 p-5 sm:p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-semibold text-slate-500">Total users</p>
                        <p class="mt-2 text-2xl font-extrabold text-[#0A2E2A]">{{ $totalUsers }}</p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-[#EAF8F2] text-[#08745D]">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0M16 5.5a3.5 3.5 0 0 1 0 6.8M18 15a5.5 5.5 0 0 1 3.5 5"/></svg>
                    </div>
                </div>
            </div>

            <div class="gt-card gt-lift gt-enter gt-enter-2 p-5 sm:p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-semibold text-slate-500">Staff members</p>
                        <p class="mt-2 text-2xl font-extrabold text-[#08745D]">{{ $totalStaff }}</p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-[#EAF8F2] text-[#08745D]">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="8" r="3.5"/><path d="M4.5 20a7.5 7.5 0 0 1 15 0"/></svg>
                    </div>
                </div>
            </div>

            <div class="gt-card gt-lift gt-enter gt-enter-3 p-5 sm:p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-semibold text-slate-500">Owners</p>
                        <p class="mt-2 text-2xl font-extrabold text-[#08745D]">{{ $totalOwners }}</p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-[#EAF8F2] text-[#08745D]">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m3 8 4.5 4 4.5-7 4.5 7L21 8l-2 11H5L3 8Z"/><path d="M6 16h12"/></svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="gt-card gt-enter gt-enter-4 p-5 sm:p-6">
            <form id="staff-filter-form" method="GET" action="{{ route('staff.index') }}" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- Search -->
                <div>
                    <label class="mb-2 block text-sm font-semibold text-slate-700">Search</label>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Name, email, phone..." class="gt-focus w-full rounded-xl border border-[#DDE9E5] bg-white px-4 py-3 text-sm text-[#0A2E2A] placeholder:text-slate-400 focus:border-[#00A87E] focus:ring-2 focus:ring-[#00C897]/20">
                </div>

                <!-- Role Filter -->
                <div>
                    <label class="mb-2 block text-sm font-semibold text-slate-700">Role</label>
                    <select name="role" class="gt-focus w-full rounded-xl border border-[#DDE9E5] bg-white px-4 py-3 text-sm text-[#0A2E2A] focus:border-[#00A87E] focus:ring-2 focus:ring-[#00C897]/20">
                        <option value="">All Roles</option>
                        <option value="owner" {{ request('role') == 'owner' ? 'selected' : '' }}>Owner</option>
                        <option value="staff" {{ request('role') == 'staff' ? 'selected' : '' }}>Staff</option>
                    </select>
                </div>

                <!-- Buttons -->
                <div class="flex items-end space-x-3">
                    <button type="submit" class="gt-focus rounded-xl bg-[#00A87E] px-5 py-3 text-sm font-bold text-white transition hover:bg-[#08745D]">
                        Apply filters
                    </button>
                    <a id="clear-staff-filters" href="{{ route('staff.index') }}" class="gt-focus rounded-xl border border-[#C8DED7] bg-white px-5 py-3 text-sm font-bold text-[#08745D] transition hover:bg-[#EAF8F2]">
                        Clear filters
                    </a>
                </div>
            </form>
        </div>

        <!-- Staff Table -->
        <div id="owner-staff-list" class="gt-card gt-enter gt-enter-4 overflow-hidden" aria-live="polite" aria-busy="false">
            <div data-staff-loading class="hidden border-b border-[#C8DED7] bg-[#EAF8F2] px-5 py-3 text-sm font-semibold text-[#08745D] sm:px-6" role="status">Updating team list...</div>
            <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-[#EAF2EF]">
                <thead class="bg-[#F8FCFA]">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Phone</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Joined</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#EAF2EF] bg-white">
                    @forelse($staff as $user)
                    <tr class="transition hover:bg-[#FBFDFC]">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-[#EAF8F2] font-extrabold text-[#08745D]">
                                    {{ strtoupper(substr($user->name, 0, 1)) }}
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm font-medium text-gray-900">{{ $user->name }}</p>
                                    @if($user->id === Auth::id())
                                    <p class="text-xs text-gray-500">(You)</p>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                            {{ $user->email }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                            {{ $user->phone ?? 'N/A' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if($user->role == 'owner')
                                <span class="rounded-full bg-[#EAF8F2] px-3 py-1 text-xs font-bold text-[#08745D]">Owner</span>
                            @else
                                <span class="rounded-full bg-[#F4F8F7] px-3 py-1 text-xs font-bold text-slate-600">Staff</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                            {{ $user->created_at->format('M d, Y') }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm space-x-2">
                            <button onclick="openEditModal({{ $user->id }}, '{{ addslashes($user->name) }}', '{{ $user->email }}', '{{ $user->phone ?? '' }}', '{{ $user->role }}')" class="gt-focus rounded-lg px-2 py-1 font-bold text-[#08745D] hover:bg-[#EAF8F2]">Edit</button>
                            @if($user->id !== Auth::id())
                            <button onclick="deleteUser({{ $user->id }})" class="gt-focus rounded-lg px-2 py-1 font-bold text-rose-600 hover:bg-rose-50">Delete</button>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-6 py-14 text-center text-slate-500">
                            <div class="flex flex-col items-center">
                                <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#EAF8F2] text-xl text-[#08745D]" aria-hidden="true">＋</span>
                                <p class="mt-3 text-sm font-bold text-[#0A2E2A]">No users found</p>
                                <p class="mt-1 text-sm">Add users to manage your team.</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
            </div>

            <!-- Pagination -->
            <div class="border-t border-[#EAF2EF] bg-[#FBFDFC] px-5 py-4 sm:px-6">
                {{ $staff->links() }}
            </div>
        </div>
    </div>

    <!-- Add/Edit User Modal -->
    <div id="userModal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-[#0A2E2A]/45 p-4 backdrop-blur-sm">
        <div class="gt-card max-h-[90vh] w-full max-w-2xl overflow-y-auto p-5 shadow-2xl sm:p-7">
            <div class="flex justify-between items-center mb-4">
                <h2 id="modalTitle" class="text-2xl font-extrabold text-[#0A2E2A]">Add User</h2>
                <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600">
                    <span class="text-2xl">&times;</span>
                </button>
            </div>

            <form id="userForm" method="POST" action="{{ route('staff.store') }}">
                @csrf
                <input type="hidden" id="userId" name="user_id">
                <input type="hidden" id="formMethod" name="_method">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Name -->
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Full Name *</label>
                        <input type="text" id="name" name="name" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-opacity-50" style="focus:ring-color: #00C897;" placeholder="John Doe">
                    </div>

                    <!-- Email -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Email *</label>
                        <input type="email" id="email" name="email" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-opacity-50" style="focus:ring-color: #00C897;" placeholder="john@example.com">
                    </div>

                    <!-- Phone -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Phone</label>
                        <input type="text" id="phone" name="phone" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-opacity-50" style="focus:ring-color: #00C897;" placeholder="+63 XXX XXX XXXX">
                    </div>

                    <!-- Role -->
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Role *</label>
                        <select id="role" name="role" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-opacity-50" style="focus:ring-color: #00C897;">
                            <option value="staff">Staff</option>
                            <option value="owner">Owner</option>
                        </select>
                    </div>

                    <!-- Password -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Password <span id="passwordRequired">*</span></label>
                        <input type="password" id="password" name="password" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-opacity-50" style="focus:ring-color: #00C897;" placeholder="••••••••">
                        <p class="text-xs text-gray-500 mt-1" id="passwordHint">Minimum 8 characters</p>
                    </div>

                    <!-- Confirm Password -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Confirm Password <span id="confirmRequired">*</span></label>
                        <input type="password" id="password_confirmation" name="password_confirmation" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-opacity-50" style="focus:ring-color: #00C897;" placeholder="••••••••">
                    </div>
                </div>

                <!-- Buttons -->
                <div class="flex space-x-3 mt-6">
                    <button type="submit" class="gt-focus flex-1 rounded-xl bg-[#00A87E] px-5 py-3 font-bold text-white transition hover:bg-[#08745D]">
                        Save User
                    </button>
                    <button type="button" onclick="closeModal()" class="gt-focus flex-1 rounded-xl border border-[#C8DED7] bg-white px-5 py-3 font-bold text-[#08745D] transition hover:bg-[#EAF8F2]">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAddModal() {
            document.getElementById('modalTitle').textContent = 'Add User';
            document.getElementById('userForm').action = '{{ route("staff.store") }}';
            document.getElementById('userId').value = '';
            document.getElementById('formMethod').value = '';
            document.getElementById('name').value = '';
            document.getElementById('email').value = '';
            document.getElementById('phone').value = '';
            document.getElementById('role').value = 'staff';
            document.getElementById('password').value = '';
            document.getElementById('password_confirmation').value = '';
            document.getElementById('password').required = true;
            document.getElementById('password_confirmation').required = true;
            document.getElementById('passwordRequired').style.display = 'inline';
            document.getElementById('confirmRequired').style.display = 'inline';
            document.getElementById('passwordHint').textContent = 'Minimum 8 characters';
            document.getElementById('userModal').classList.remove('hidden');
        }

        function openEditModal(id, name, email, phone, role) {
            document.getElementById('modalTitle').textContent = 'Edit User';
            document.getElementById('userForm').action = '/staff/' + id;
            document.getElementById('userId').value = id;
            document.getElementById('formMethod').value = 'PUT';
            document.getElementById('name').value = name;
            document.getElementById('email').value = email;
            document.getElementById('phone').value = phone;
            document.getElementById('role').value = role;
            document.getElementById('password').value = '';
            document.getElementById('password_confirmation').value = '';
            document.getElementById('password').required = false;
            document.getElementById('password_confirmation').required = false;
            document.getElementById('passwordRequired').style.display = 'none';
            document.getElementById('confirmRequired').style.display = 'none';
            document.getElementById('passwordHint').textContent = 'Leave blank to keep current password';
            document.getElementById('userModal').classList.remove('hidden');
        }

        function closeModal() {
            document.getElementById('userModal').classList.add('hidden');
        }

        function deleteUser(id) {
            if (confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
                fetch('/staff/' + id, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert(data.message || 'Error deleting user');
                    }
                })
                .catch(error => {
                    alert('Error deleting user');
                });
            }
        }

        (() => {
            const form = document.getElementById('staff-filter-form');
            const clearLink = document.getElementById('clear-staff-filters');
            let requestController = null;
            let requestSequence = 0;

            const cleanUrl = (value) => {
                const url = new URL(value, window.location.origin);
                url.searchParams.delete('fragment');
                return url;
            };

            async function loadStaff(value, updateHistory = false) {
                const displayUrl = cleanUrl(value);
                requestController?.abort();
                const controller = new AbortController();
                const sequence = ++requestSequence;
                requestController = controller;

                const currentList = document.getElementById('owner-staff-list');
                const loadingMessage = currentList.querySelector('[data-staff-loading]');
                currentList.setAttribute('aria-busy', 'true');
                currentList.classList.add('opacity-60');
                loadingMessage.textContent = 'Updating team list...';
                loadingMessage.classList.remove('hidden');

                try {
                    const response = await fetch(displayUrl, {
                        headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html'},
                        signal: controller.signal,
                    });
                    if (!response.ok) throw new Error('Staff list could not be loaded.');

                    const responseDocument = new DOMParser().parseFromString(await response.text(), 'text/html');
                    const updatedList = responseDocument.getElementById('owner-staff-list');
                    if (!updatedList) throw new Error('Staff response was incomplete.');
                    if (sequence !== requestSequence) return;

                    currentList.replaceWith(updatedList);
                    if (updateHistory) {
                        window.history.pushState({}, '', displayUrl.pathname + displayUrl.search);
                    }
                } catch (error) {
                    if (error.name !== 'AbortError' && sequence === requestSequence) {
                        currentList.setAttribute('aria-busy', 'false');
                        currentList.classList.remove('opacity-60');
                        loadingMessage.textContent = 'Could not refresh the staff list. Please try again.';
                        loadingMessage.classList.remove('hidden');
                    }
                } finally {
                    if (requestController === controller) requestController = null;
                }
            }

            form.addEventListener('submit', (event) => {
                event.preventDefault();
                const url = new URL(form.action, window.location.origin);
                new FormData(form).forEach((value, key) => {
                    if (String(value).trim()) url.searchParams.set(key, value);
                });
                loadStaff(url, true);
            });

            clearLink.addEventListener('click', (event) => {
                event.preventDefault();
                form.querySelector('[name="search"]').value = '';
                form.querySelector('[name="role"]').value = '';
                loadStaff(clearLink.href, true);
            });

            document.addEventListener('click', (event) => {
                if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
                const pageLink = event.target.closest('#owner-staff-list nav[role="navigation"] a[href], #owner-staff-list .pagination a[href]');
                if (!pageLink) return;
                event.preventDefault();
                loadStaff(pageLink.href, true);
            });

            window.addEventListener('popstate', () => {
                const url = new URL(window.location.href);
                form.querySelector('[name="search"]').value = url.searchParams.get('search') || '';
                form.querySelector('[name="role"]').value = url.searchParams.get('role') || '';
                loadStaff(url);
            });
        })();

        // Close modal when clicking outside
        document.getElementById('userModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
        </main>
    </div>
</body>
</html>
