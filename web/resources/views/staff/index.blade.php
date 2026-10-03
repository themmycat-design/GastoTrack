<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Management - GastoTrack</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen lg:flex">
        @include('layouts.owner-navigation')

        <!-- Main Content -->
        <div class="min-w-0 flex-1">
            <div class="mx-auto max-w-7xl px-5 py-6 sm:px-8 sm:py-8">
        <!-- Header -->
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Staff Management</h1>
                <p class="text-gray-600 mt-1">Manage users and access control</p>
            </div>
            <button onclick="openAddModal()" class="px-6 py-3 text-white font-semibold rounded-lg shadow-md hover:shadow-lg transition-all" style="background-color: #00C897;">
                + Add User
            </button>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 mb-1">Total Users</p>
                        <p class="text-2xl font-bold text-gray-900">{{ $totalUsers }}</p>
                    </div>
                    <div class="w-12 h-12 rounded-full flex items-center justify-center" style="background-color: rgba(0, 200, 151, 0.1);">
                        <span class="text-2xl">👥</span>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 mb-1">Staff Members</p>
                        <p class="text-2xl font-bold" style="color: #00C897;">{{ $totalStaff }}</p>
                    </div>
                    <div class="w-12 h-12 rounded-full flex items-center justify-center" style="background-color: rgba(0, 200, 151, 0.1);">
                        <span class="text-2xl">👤</span>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 mb-1">Owners</p>
                        <p class="text-2xl font-bold text-purple-600">{{ $totalOwners }}</p>
                    </div>
                    <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center">
                        <span class="text-2xl text-purple-600">👑</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
            <form method="GET" action="{{ route('staff.index') }}" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- Search -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Name, email, phone..." class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-opacity-50" style="focus:ring-color: #00C897;">
                </div>

                <!-- Role Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Role</label>
                    <select name="role" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-opacity-50" style="focus:ring-color: #00C897;">
                        <option value="">All Roles</option>
                        <option value="owner" {{ request('role') == 'owner' ? 'selected' : '' }}>Owner</option>
                        <option value="staff" {{ request('role') == 'staff' ? 'selected' : '' }}>Staff</option>
                    </select>
                </div>

                <!-- Buttons -->
                <div class="flex items-end space-x-3">
                    <button type="submit" class="px-6 py-2 text-white font-semibold rounded-lg hover:opacity-90" style="background-color: #00C897;">
                        Apply Filters
                    </button>
                    <a href="{{ route('staff.index') }}" class="px-6 py-2 bg-gray-200 text-gray-700 font-semibold rounded-lg hover:bg-gray-300">
                        Clear
                    </a>
                </div>
            </form>
        </div>

        <!-- Staff Table -->
        <div class="bg-white rounded-lg shadow-sm overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Phone</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Joined</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($staff as $user)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="w-10 h-10 rounded-full flex items-center justify-center text-white font-bold" style="background-color: {{ $user->role == 'owner' ? '#9333EA' : '#00C897' }};">
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
                                <span class="px-3 py-1 text-xs font-semibold text-purple-800 bg-purple-100 rounded-full">👑 Owner</span>
                            @else
                                <span class="px-3 py-1 text-xs font-semibold text-green-800 bg-green-100 rounded-full">👤 Staff</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                            {{ $user->created_at->format('M d, Y') }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm space-x-2">
                            <button onclick="openEditModal({{ $user->id }}, '{{ addslashes($user->name) }}', '{{ $user->email }}', '{{ $user->phone ?? '' }}', '{{ $user->role }}')" class="text-blue-600 hover:text-blue-800 font-medium">Edit</button>
                            @if($user->id !== Auth::id())
                            <button onclick="deleteUser({{ $user->id }})" class="text-red-600 hover:text-red-800 font-medium">Delete</button>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                            <div class="flex flex-col items-center">
                                <span class="text-4xl mb-2">👥</span>
                                <p class="text-lg font-medium">No users found</p>
                                <p class="text-sm mt-1">Add users to manage your team</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>

            <!-- Pagination -->
            <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
                {{ $staff->links() }}
            </div>
        </div>
    </div>

    <!-- Add/Edit User Modal -->
    <div id="userModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl p-6 max-h-screen overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h2 id="modalTitle" class="text-2xl font-bold text-gray-900">Add User</h2>
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
                    <button type="submit" class="flex-1 px-6 py-3 text-white font-semibold rounded-lg hover:opacity-90" style="background-color: #00C897;">
                        Save User
                    </button>
                    <button type="button" onclick="closeModal()" class="flex-1 px-6 py-3 bg-gray-200 text-gray-700 font-semibold rounded-lg hover:bg-gray-300">
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

        // Close modal when clicking outside
        document.getElementById('userModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
            </div>
        </div>
    </div>
</body>
</html>
