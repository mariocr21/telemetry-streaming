import type { PageProps } from '@inertiajs/core';
import type { LucideIcon } from 'lucide-vue-next';
import type { Config } from 'ziggy-js';

// ===== INTERFACES BASE =====

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    // Campos adicionales para usuarios del sistema
    client_id?: number;
    role?: 'SA' | 'CA' | 'CU'; // Super Admin, Client Admin, Client User
    role_label?: string;
    is_active?: boolean;
}

export interface Auth {
    user: User;
}

export interface Client {
    id: number;
    first_name: string;
    last_name: string;
    full_name: string;
    email: string;
    phone?: string;
    address?: string;
    city?: string;
    state?: string;
    zip_code?: string;
    country?: string;
    company?: string;
    job_title?: string;
    created_at: string;
    updated_at: string;
    users?: User[];
    users_count?: number;
    can?: {
        view: boolean;
        update: boolean;
        delete: boolean;
    };
}

// ===== INTERFACES DE NAVEGACIÓN =====

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href: string;
    icon?: LucideIcon;
    isActive?: boolean;
}

// ===== INTERFACES DE PAGINACIÓN =====

export interface PaginationLink {
    url?: string;
    label: string;
    active: boolean;
}

export interface PaginationMeta {
    current_page: number;
    from?: number;
    last_page: number;
    links: PaginationLink[];
    path: string;
    per_page: number;
    to?: number;
    total: number;
}

export interface PaginatedData<T> {
    data: T[];
    links: PaginationLink[];
    meta: PaginationMeta;
}

// ===== INTERFACES DE MENSAJES =====

export interface FlashMessage {
    message?: string;
    type?: 'success' | 'error' | 'warning' | 'info';
}

export interface FlashSuccess {
    message?: string;
    user_created?: boolean;
    user_email?: string;
    user_password?: string;
    user_name?: string;
    user_role?: string;
    user_role_label?: string;
}

// ===== INTERFACES DE FILTROS =====

export interface ClientFilters {
    search?: string;
    sort?: string;
    direction?: 'asc' | 'desc';
}

// ===== INTERFACES DE PERMISOS =====

export interface Permissions {
    create_client?: boolean;
    edit_client?: boolean;
    delete_client?: boolean;
    view_client?: boolean;
}

// ===== SHARED DATA PARA INERTIA =====

export interface SharedData extends PageProps {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    ziggy: Config & { location: string };
    sidebarOpen: boolean;
    flash?: FlashMessage | FlashSuccess;
    errors?: Record<string, string>;
}

// ===== TIPOS ESPECÍFICOS PARA COMPONENTES =====

export interface UserCredentials {
    userEmail: string;
    userPassword: string;
    userName: string;
    userRole: string;
    userRoleLabel: string;
}

export interface ClientShowProps {
    client: Client & {
        users: User[];
        can: {
            view: boolean;
            update: boolean;
            delete: boolean;
        };
    };
}

export interface ClientIndexProps {
    clients: PaginatedData<Client>;
    filters: ClientFilters;
    can: Permissions;
}

// ===== ALIAS DE TIPOS =====

export type BreadcrumbItemType = BreadcrumbItem;

// ===== EXTENSIONES GLOBALES =====

declare global {
    namespace App {
        interface PageProps extends Record<string, any> {
            auth: Auth;
            flash?: FlashMessage | FlashSuccess;
            errors?: Record<string, string>;
            ziggy: Config & { location: string };
        }
    }
}

// ===== TIPOS DE RUTAS (OPCIONAL) =====

export interface RouteParams {
    [key: string]: string | number;
}

export interface RouteOptions {
    params?: RouteParams;
    query?: Record<string, any>;
}

// ===== INTERFACES DE DISPOSITIVOS =====

export interface DeviceInventory {
    id: number;
    serial_number: string;
    device_uuid: string;
    model: string;
    hardware_version?: string;
    firmware_version?: string;
    status: 'available' | 'sold' | 'maintenance' | 'retired';
    manufactured_date?: string;
    sold_date?: string;
    notes?: string;
    created_at: string;
    updated_at?: string;
    client_devices_count?: number;
    client_devices?: ClientDevice[];
    can?: {
        view: boolean;
        update: boolean;
        delete: boolean;
    };
}

export interface ClientDevice {
    id: number;
    device_inventory_id: number;
    client_id: number;
    device_name: string;
    mac_address: string;
    status: 'pending' | 'active' | 'inactive' | 'maintenance' | 'retired';
    activated_at?: string;
    last_ping?: string;
    device_config?: any;
    created_at: string;
    updated_at: string;
    device_inventory?: DeviceInventory;
    vehicle?: Vehicle;
    can?: {
        view: boolean;
        update: boolean;
        delete: boolean;
    };
    client?: Client;
}

export interface Vehicle {
    id: number;
    client_id: number;
    client_device_id: number;
    vin?: string;
    protocol?: string;
    supported_pids?: any;
    make?: string;
    model?: string;
    year?: number;
    license_plate?: string;
    color?: string;
    nickname?: string;
    auto_detected?: boolean;
    is_configured?: boolean;
    first_reading_at?: string;
    last_reading_at?: string;
}

export interface Sensor {
    id: number;
    pid: string;
    name: string;
    description?: string;
    category?: string;
    unit?: string;
    data_type?: string;
    min_value?: number;
    max_value?: number;
    requires_calculation?: boolean;
    calculation_formula?: string;
    data_bytes?: number;
    is_standard?: boolean;
    notes?: string;
}

// ===== INTERFACES PARA PROPS DE COMPONENTES DE DISPOSITIVOS =====

export interface DeviceIndexProps {
    client: {
        id: number;
        full_name: string;
        email: string;
    };
    devices: PaginatedData<ClientDevice>;
    filters: {
        search?: string;
    };
    can: {
        create_device: boolean;
    };
}

// ===== INTERFACES PARA PROPS DE COMPONENTES DE INVENTARIO =====

export interface DeviceInventoryIndexProps {
    devices: PaginatedData<DeviceInventory>;
    filters: {
        search?: string;
        status?: string;
        sort?: string;
        direction?: string;
    };
    filterOptions: {
        statuses: string[];
    };
    can: {
        create_device: boolean;
    };
}

export interface DeviceInventoryCreateProps {
    message?: string;
}

export interface DeviceInventoryShowProps {
    device: DeviceInventory;
}

export interface DeviceInventoryEditProps {
    device: DeviceInventory;
}

// ===== INTERFACES PARA FORMULARIOS DE INVENTARIO =====

export interface DeviceInventoryFormData {
    serial_number: string;
    device_uuid: string;
    model: string;
    hardware_version?: string;
    firmware_version?: string;
    status: 'available' | 'sold' | 'maintenance' | 'retired';
    manufactured_date?: string;
    sold_date?: string;
    notes?: string;
}

export interface ClientFormData {
    first_name: string;
    last_name: string;
    email: string;
    phone?: string;
    address?: string;
    city?: string;
    state?: string;
    zip_code?: string;
    country?: string;
    company?: string;
    job_title?: string;
}

export interface UserFormData {
    name: string;
    email: string;
    password?: string;
    role: 'SA' | 'CA' | 'CU';
    is_active: boolean;
    client_id: number;
}

// ===== TIPOS PARA RESPUESTAS DE API =====

export interface ApiResponse<T = any> {
    data: T;
    message?: string;
    errors?: Record<string, string[]>;
    status: number;
}

export interface ValidationErrors {
    [field: string]: string[];
}

// ===== TIPOS PARA EVENTOS =====

export interface TableSortEvent {
    column: string;
    direction: 'asc' | 'desc';
}

export interface SearchEvent {
    query: string;
}

// ===== TIPOS PARA ESTADOS =====

export type LoadingState = 'idle' | 'loading' | 'success' | 'error';

export interface ComponentState {
    loading: LoadingState;
    error?: string;
    data?: any;
}

// ===== EXPORTS ADICIONALES =====

export type { PageProps };
export type { Config };
export type { LucideIcon };


// ===== INTERFACES PARA PROPS DE COMPONENTES DE DISPOSITIVOS =====

export interface DeviceCreateProps {
    client: {
        id: number;
        full_name: string;
        email: string;
    };
    availableDevices: DeviceInventory[];
}

export interface DeviceShowProps {
    client: {
        id: number;
        full_name: string;
        email: string;
    };
    device: ClientDevice;
}
