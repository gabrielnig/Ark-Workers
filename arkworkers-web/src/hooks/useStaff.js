import { useQuery, useMutation } from '@tanstack/react-query';
import {
  fetchStaff,
  fetchDepartmentOptions,
  updateAdminStatus,
  joinDepartment,
  leaveDepartment,
} from '../api/staff.js';
import { queryClient } from '../queryClient.js';

export function useStaff() {
  return useQuery({
    queryKey: ['staff'],
    queryFn: fetchStaff,
  });
}

export function useDepartmentOptions(enabled) {
  return useQuery({
    queryKey: ['staffDepartmentOptions'],
    queryFn: fetchDepartmentOptions,
    enabled,
  });
}

export function useUpdateAdminStatus() {
  return useMutation({
    mutationFn: ({ userId, isAdmin }) => updateAdminStatus(userId, isAdmin),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['staff'] }),
  });
}

export function useJoinDepartment() {
  return useMutation({
    mutationFn: ({ userId, departmentId, roleId }) => joinDepartment(userId, departmentId, roleId),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['staff'] }),
  });
}

export function useLeaveDepartment() {
  return useMutation({
    mutationFn: ({ userId, departmentId }) => leaveDepartment(userId, departmentId),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['staff'] }),
  });
}
