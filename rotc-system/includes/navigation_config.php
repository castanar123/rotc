<?php
/**
 * Centralized Navigation Configuration
 * Defines role-based navigation menus and routing
 */

class NavigationConfig {
    private static $base_url;
    private static $current_page;
    private static $current_dir;
    
    public static function init() {
        self::$base_url = '/rotc-system/';
        self::$current_page = basename($_SERVER['PHP_SELF']);
        self::$current_dir = basename(dirname($_SERVER['PHP_SELF']));
    }
    
    /**
     * Get navigation menu for specific role
     */
    public static function getMenuForRole($role) {
        self::init();
        
        switch($role) {
            case 'admin':
                return self::getAdminMenu();
            case 'officer':
            case 'instructor':
                return self::getOfficerMenu();
            case 'cadet':
                return self::getCadetMenu();
            default:
                return [];
        }
    }
    
    /**
     * Admin Navigation Menu
     */
    private static function getAdminMenu() {
        return [
            'main' => [
                'title' => 'COMMAND CENTER',
                'items' => [
                    [
                        'icon' => 'fas fa-tachometer-alt',
                        'text' => 'Dashboard',
                        'url' => 'admin_dashboard.php',
                        'active' => self::$current_page === 'admin_dashboard.php'
                    ],
                    [
                        'icon' => 'fas fa-qrcode',
                        'text' => 'QR Attendance',
                        'url' => 'attendance/dashboard.php',
                        'active' => self::$current_dir === 'attendance'
                    ],
                    [
                        'icon' => 'fas fa-users-cog',
                        'text' => 'User Management',
                        'url' => 'user_management.php',
                        'active' => self::$current_page === 'user_management.php'
                    ]
                ]
            ],
            'operations' => [
                'title' => 'OPERATIONS',
                'items' => [
                    [
                        'icon' => 'fas fa-chart-bar',
                        'text' => 'Reports',
                        'url' => 'reports/view_report.php',
                        'active' => self::$current_dir === 'reports'
                    ],
                    [
                        'icon' => 'fas fa-bullhorn',
                        'text' => 'Announcements',
                        'url' => 'announcements.php',
                        'active' => self::$current_page === 'announcements.php'
                    ],
                    [
                        'icon' => 'fas fa-graduation-cap',
                        'text' => 'Grade Management',
                        'url' => 'grades/manage_grades.php',
                        'active' => self::$current_dir === 'grades'
                    ],
                    [
                        'icon' => 'fas fa-id-card',
                        'text' => 'Profile Management',
                        'url' => 'profile_management.php',
                        'active' => self::$current_page === 'profile_management.php'
                    ]
                ]
            ],
            'system' => [
                'title' => 'SYSTEM',
                'items' => [
                    [
                        'icon' => 'fas fa-cog',
                        'text' => 'Settings',
                        'url' => 'settings.php',
                        'active' => self::$current_page === 'settings.php'
                    ],
                    [
                        'icon' => 'fas fa-sign-out-alt',
                        'text' => 'Logout',
                        'url' => 'logout.php',
                        'active' => false
                    ]
                ]
            ]
        ];
    }
    
    /**
     * Officer/Instructor Navigation Menu
     */
    private static function getOfficerMenu() {
        return [
            'command' => [
                'title' => 'COMMAND',
                'items' => [
                    [
                        'icon' => 'fas fa-tachometer-alt',
                        'text' => 'Dashboard',
                        'url' => 'officer_dashboard.php',
                        'active' => self::$current_page === 'officer_dashboard.php'
                    ],
                    [
                        'icon' => 'fas fa-users',
                        'text' => 'My Platoon',
                        'url' => 'my_platoons.php',
                        'active' => self::$current_page === 'my_platoons.php'
                    ],
                    [
                        'icon' => 'fas fa-qrcode',
                        'text' => 'QR Attendance',
                        'url' => 'attendance/dashboard.php',
                        'active' => self::$current_dir === 'attendance'
                    ]
                ]
            ],
            'operations' => [
                'title' => 'OPERATIONS',
                'items' => [
                    [
                        'icon' => 'fas fa-calendar-alt',
                        'text' => 'Training Schedule',
                        'url' => 'training_schedule.php',
                        'active' => self::$current_page === 'training_schedule.php'
                    ],
                    [
                        'icon' => 'fas fa-chart-line',
                        'text' => 'Reports',
                        'url' => 'reports/view_report.php',
                        'active' => self::$current_dir === 'reports'
                    ],
                    [
                        'icon' => 'fas fa-graduation-cap',
                        'text' => 'Grades',
                        'url' => 'grades/view_grades.php',
                        'active' => self::$current_dir === 'grades'
                    ]
                ]
            ],
            'tools' => [
                'title' => 'TOOLS',
                'items' => [
                    [
                        'icon' => 'fas fa-bullhorn',
                        'text' => 'Announcements',
                        'url' => 'announcements.php',
                        'active' => self::$current_page === 'announcements.php'
                    ],
                    [
                        'icon' => 'fas fa-sign-out-alt',
                        'text' => 'Logout',
                        'url' => 'logout.php',
                        'active' => false
                    ]
                ]
            ]
        ];
    }
    
    /**
     * Cadet Navigation Menu
     */
    private static function getCadetMenu() {
        return [
            'main' => [
                'title' => 'MAIN',
                'items' => [
                    [
                        'icon' => 'fas fa-tachometer-alt',
                        'text' => 'Dashboard',
                        'url' => 'cadet_dashboard.php',
                        'active' => self::$current_page === 'cadet_dashboard.php'
                    ],
                    [
                        'icon' => 'fas fa-user',
                        'text' => 'My Profile',
                        'url' => 'my_profile.php',
                        'active' => self::$current_page === 'my_profile.php'
                    ],
                    [
                        'icon' => 'fas fa-qrcode',
                        'text' => 'Attendance',
                        'url' => 'attendance/scan.php',
                        'active' => self::$current_dir === 'attendance'
                    ]
                ]
            ],
            'academic' => [
                'title' => 'ACADEMIC',
                'items' => [
                    [
                        'icon' => 'fas fa-graduation-cap',
                        'text' => 'My Grades',
                        'url' => 'grades/view_grades.php',
                        'active' => self::$current_dir === 'grades'
                    ],
                    [
                        'icon' => 'fas fa-calendar',
                        'text' => 'Schedule',
                        'url' => 'schedule.php',
                        'active' => self::$current_page === 'schedule.php'
                    ],
                    [
                        'icon' => 'fas fa-bullhorn',
                        'text' => 'Announcements',
                        'url' => 'announcements.php',
                        'active' => self::$current_page === 'announcements.php'
                    ]
                ]
            ],
            'account' => [
                'title' => 'ACCOUNT',
                'items' => [
                    [
                        'icon' => 'fas fa-sign-out-alt',
                        'text' => 'Logout',
                        'url' => 'logout.php',
                        'active' => false
                    ]
                ]
            ]
        ];
    }
    
    /**
     * Generate sidebar HTML for given role
     */
    public static function generateSidebar($role, $title = null) {
        $menu = self::getMenuForRole($role);
        $roleTitle = $title ?: ucfirst($role) . ' Command';
        
        $html = '<aside class="sidebar" id="sidebar">';
        $html .= '<div class="sidebar-header">';
        $html .= '<div class="logo">';
        $html .= '<div class="logo-icon"><i class="fas fa-shield-alt"></i></div>';
        $html .= '<span class="logo-text">' . htmlspecialchars($roleTitle) . '</span>';
        $html .= '</div>';
        $html .= '<button class="sidebar-toggle" id="sidebarToggle">';
        $html .= '<i class="fas fa-bars"></i>';
        $html .= '</button>';
        $html .= '</div>';
        
        $html .= '<nav class="sidebar-nav">';
        
        foreach ($menu as $section) {
            $html .= '<div class="nav-section">';
            $html .= '<div class="nav-title">' . htmlspecialchars($section['title']) . '</div>';
            
            foreach ($section['items'] as $item) {
                $activeClass = $item['active'] ? ' active' : '';
                $html .= '<a href="' . htmlspecialchars(self::$base_url . $item['url']) . '" class="nav-link' . $activeClass . '">';
                $html .= '<i class="' . htmlspecialchars($item['icon']) . '"></i>';
                $html .= '<span>' . htmlspecialchars($item['text']) . '</span>';
                $html .= '</a>';
            }
            
            $html .= '</div>';
        }
        
        $html .= '</nav>';
        $html .= '</aside>';
        
        return $html;
    }
    
    /**
     * Get quick actions for role
     */
    public static function getQuickActions($role) {
        switch($role) {
            case 'admin':
                return [
                    [
                        'icon' => 'fas fa-qrcode',
                        'title' => 'Quick Scan',
                        'description' => 'Scan QR codes instantly',
                        'action' => 'openQRScanner()',
                        'color' => 'primary'
                    ],
                    [
                        'icon' => 'fas fa-user-plus',
                        'title' => 'Add User',
                        'description' => 'Register new user',
                        'action' => 'window.location.href="register.php"',
                        'color' => 'success'
                    ],
                    [
                        'icon' => 'fas fa-chart-bar',
                        'title' => 'View Reports',
                        'description' => 'System analytics',
                        'action' => 'window.location.href="reports/view_report.php"',
                        'color' => 'info'
                    ],
                    [
                        'icon' => 'fas fa-cog',
                        'title' => 'Settings',
                        'description' => 'System configuration',
                        'action' => 'window.location.href="profile_management.php"',
                        'color' => 'warning'
                    ]
                ];
                
            case 'officer':
            case 'instructor':
                return [
                    [
                        'icon' => 'fas fa-users',
                        'title' => 'View Cadets',
                        'description' => 'Manage your platoon',
                        'action' => 'window.location.href="my_platoons.php"',
                        'color' => 'primary'
                    ],
                    [
                        'icon' => 'fas fa-qrcode',
                        'title' => 'QR Scanner',
                        'description' => 'Take attendance',
                        'action' => 'window.location.href="attendance/dashboard.php"',
                        'color' => 'success'
                    ],
                    [
                        'icon' => 'fas fa-calendar-alt',
                        'title' => 'Training Schedule',
                        'description' => 'Manage training events',
                        'action' => 'window.location.href="training_schedule.php"',
                        'color' => 'info'
                    ],
                    [
                        'icon' => 'fas fa-chart-line',
                        'title' => 'Reports',
                        'description' => 'Platoon performance',
                        'action' => 'window.location.href="reports/view_report.php"',
                        'color' => 'warning'
                    ]
                ];
                
            case 'cadet':
                return [
                    [
                        'icon' => 'fas fa-qrcode',
                        'title' => 'Scan Attendance',
                        'description' => 'Mark your attendance',
                        'action' => 'window.location.href="attendance/scan.php"',
                        'color' => 'primary'
                    ],
                    [
                        'icon' => 'fas fa-graduation-cap',
                        'title' => 'My Grades',
                        'description' => 'View academic progress',
                        'action' => 'window.location.href="grades/view_grades.php"',
                        'color' => 'success'
                    ],
                    [
                        'icon' => 'fas fa-user',
                        'title' => 'My Profile',
                        'description' => 'Update personal info',
                        'action' => 'window.location.href="my_profile.php"',
                        'color' => 'info'
                    ],
                    [
                        'icon' => 'fas fa-calendar',
                        'title' => 'Schedule',
                        'description' => 'View training schedule',
                        'action' => 'window.location.href="schedule.php"',
                        'color' => 'warning'
                    ]
                ];
                
            default:
                return [];
        }
    }
}
?>