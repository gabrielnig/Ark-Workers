import { apiFetch } from './client.js';

export async function fetchStaff() {
  const { data } = await apiFetch('/api/staff');
  return data;
}

export async function fetchDepartmentOptions() {
  const { data } = await apiFetch('/api/staff/department-options');
  return data;
}

export async function updateAdminStatus(userId, isAdmin) {
  const { data } = await apiFetch(`/api/staff/${userId}/admin`, {
    method: 'PATCH',
    body: JSON.stringify({ is_admin: isAdmin }),
  });
  return data;
}

export async function joinDepartment(userId, departmentId, roleId) {
  const { data } = await apiFetch(`/api/staff/${userId}/departments`, {
    method: 'POST',
    body: JSON.stringify({ department_id: departmentId, role_id: roleId }),
  });
  return data;
}

export async function leaveDepartment(userId, departmentId) {
  const { data } = await apiFetch(`/api/staff/${userId}/departments/${departmentId}`, {
    method: 'DELETE',
  });
  return data;
}
