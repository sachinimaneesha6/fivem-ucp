# FiveM QBCore Web UCP

A modern, responsive web-based User Control Panel for FiveM QBCore servers built with PHP, HTML, and Tailwind CSS.

## Features

- **User Authentication**: Secure login system using existing QBCore user accounts
- **Character Management**: View and manage all your characters
- **Character Dashboard**: Detailed character information including stats, job, gang, and financial data
- **Inventory System**: Visual inventory grid showing all character items
- **Location Tracking**: View last known character positions
- **Support Ticket System**: Submit and track support requests
- **Responsive Design**: Works perfectly on both mobile and desktop devices
- **Modern UI**: Clean, modern interface with FiveM-style design aesthetics

## Installation

1. **Upload Files**: Upload all files to your web server directory
2. **Database Configuration**: Edit `config/database.php` with your database credentials
3. **Apache Configuration**: Ensure mod_rewrite is enabled for pretty URLs
4. **Permissions**: Set appropriate file permissions (644 for files, 755 for directories)

## Database Configuration

Update the database connection settings in `config/database.php`:

```php
private $host = 'localhost';        // Your database host
private $db_name = 'fivem';         // Your QBCore database name
private $username = 'root';         // Database username
private $password = '';             // Database password
```

## Requirements

- **PHP 7.4+** with PDO MySQL extension
- **Apache 2.4+** with mod_rewrite enabled
- **MySQL/MariaDB** database with QBCore schema
- **SSL Certificate** (recommended for production)

## Security Features

- Password hashing verification
- SQL injection prevention with prepared statements
- XSS protection with input sanitization
- Session management
- CSRF protection ready
- Security headers via .htaccess

## File Structure

```
├── config/
│   ├── database.php          # Database connection
│   └── auth.php              # Authentication system
├── includes/
│   ├── header.php            # HTML head and navigation
│   ├── navbar.php            # Navigation bar
│   └── footer.php            # Footer
├── api/
│   └── map_data.php          # API for location data
├── index.php                 # Login page
├── dashboard.php             # Main dashboard
├── characters.php            # Character selection
├── character_detail.php      # Detailed character view
├── inventory.php             # Inventory management
├── tickets.php               # Support ticket system
├── logout.php                # Logout handler
└── .htaccess                 # Apache configuration
```

## Usage

1. **Login**: Use your existing QBCore account credentials
2. **Dashboard**: View server statistics and character overview
3. **Characters**: Select and manage your characters
4. **Character Details**: View comprehensive character information
5. **Inventory**: Browse character items in a visual grid
6. **Support**: Submit and track support tickets

## Customization

### Styling
- Modify Tailwind configuration in `includes/header.php`
- Update color scheme by changing the `fivem` color palette
- Add custom CSS in the `<style>` section

### Database Integration
- All data is pulled directly from your existing QBCore database
- No additional database modifications required
- Supports all standard QBCore tables and structures

### Features to Add
- Vehicle management interface
- Property/apartment management
- Banking system integration
- Admin panel for ticket management
- Real-time notifications
- Character creation interface

## Security Considerations

- Always use HTTPS in production
- Regularly update PHP and dependencies
- Monitor access logs for suspicious activity
- Implement rate limiting for login attempts
- Consider adding two-factor authentication

## Support

This UCP integrates seamlessly with your existing QBCore server without requiring any server-side modifications. All character data, inventory, vehicles, and other information is read directly from your QBCore database.

## License

This project is open source and available under the MIT License.