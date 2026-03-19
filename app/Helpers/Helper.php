<?php

// File: app/Helpers/Helper.php

if (!function_exists('format_phone')) {
    /**
     * Format phone number to standard display
     * Example: 09123456789 => 09-123456789
     */
    function format_phone(string $phone): string
    {
        if (strlen($phone) === 11) {
            return substr($phone, 0, 2) . '-' . substr($phone, 2);
        }
        return $phone;
    }
}

if (!function_exists('user_status_badge')) {
    /**
     * Return a badge class based on user status
     */
    function user_status_badge(string $status): string
    {
        return $status === 'Active' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700';
    }
}

if (!function_exists('role_badge')) {
    /**
     * Return a badge class based on user role
     */
    function role_badge(string $role): string
    {
        switch (strtolower($role)) {
            case 'admin':
                return 'bg-red-100 text-red-700';
            case 'staff':
                return 'bg-sky-100 text-sky-700';
            default:
                return 'bg-gray-100 text-gray-700';
        }
    }
}

if (!function_exists('current_datetime')) {
    /**
     * Return current datetime in Y-m-d H:i:s format
     */
    function current_datetime(): string
    {
        return now()->format('Y-m-d H:i:s');
    }
}

if (!function_exists('truncateCsvRow')) {
    function truncateCsvRow(array $row, array $columnsLength): array {
        $result = [];
        foreach ($columnsLength as $index => $maxLength) {
            $result[$index] = isset($row[$index]) ? substr($row[$index], 0, $maxLength) : null;
        }
        return $result;
    }
}
