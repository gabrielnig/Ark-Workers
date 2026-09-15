import { useQuery, useMutation } from '@tanstack/react-query';
import {
  fetchVehicles,
  fetchVehicle,
  createVehicle,
  updateVehicle,
  deleteVehicle,
  fetchVehicleLogs,
  createVehicleLog,
  deleteVehicleLog,
  fetchVehicleIncidents,
  createVehicleIncident,
  updateVehicleIncident,
  uploadIncidentPhoto,
  deleteIncidentPhoto,
} from '../api/vehicles.js';
import { queryClient } from '../queryClient.js';

export function useVehicles() {
  return useQuery({ queryKey: ['vehicles'], queryFn: fetchVehicles });
}

export function useVehicle(vehicleId) {
  return useQuery({
    queryKey: ['vehicles', vehicleId],
    queryFn: () => fetchVehicle(vehicleId),
    enabled: !!vehicleId,
  });
}

export function useCreateVehicle() {
  return useMutation({
    mutationFn: createVehicle,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['vehicles'] }),
  });
}

export function useUpdateVehicle(vehicleId) {
  return useMutation({
    mutationFn: (payload) => updateVehicle(vehicleId, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['vehicles'] });
      queryClient.invalidateQueries({ queryKey: ['vehicles', vehicleId] });
    },
  });
}

export function useDeleteVehicle() {
  return useMutation({
    mutationFn: deleteVehicle,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['vehicles'] }),
  });
}

export function useVehicleLogs(vehicleId) {
  return useQuery({
    queryKey: ['vehicleLogs', vehicleId],
    queryFn: () => fetchVehicleLogs(vehicleId),
    enabled: !!vehicleId,
  });
}

export function useCreateVehicleLog(vehicleId) {
  return useMutation({
    mutationFn: (payload) => createVehicleLog(vehicleId, payload),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['vehicleLogs', vehicleId] }),
  });
}

export function useDeleteVehicleLog(vehicleId) {
  return useMutation({
    mutationFn: deleteVehicleLog,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['vehicleLogs', vehicleId] }),
  });
}

export function useVehicleIncidents(vehicleId) {
  return useQuery({
    queryKey: ['vehicleIncidents', vehicleId],
    queryFn: () => fetchVehicleIncidents(vehicleId),
    enabled: !!vehicleId,
  });
}

export function useCreateVehicleIncident(vehicleId) {
  return useMutation({
    mutationFn: (payload) => createVehicleIncident(vehicleId, payload),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['vehicleIncidents', vehicleId] }),
  });
}

export function useUpdateVehicleIncident(vehicleId) {
  return useMutation({
    mutationFn: ({ incidentId, ...payload }) => updateVehicleIncident(incidentId, payload),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['vehicleIncidents', vehicleId] }),
  });
}

export function useUploadIncidentPhoto(vehicleId) {
  return useMutation({
    mutationFn: ({ incidentId, stage, file }) => uploadIncidentPhoto(incidentId, stage, file),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['vehicleIncidents', vehicleId] }),
  });
}

export function useDeleteIncidentPhoto(vehicleId) {
  return useMutation({
    mutationFn: deleteIncidentPhoto,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['vehicleIncidents', vehicleId] }),
  });
}
