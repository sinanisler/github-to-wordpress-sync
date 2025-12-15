# Github to WordPress Sync

GitHub to WordPress Sync: Streamline theme & plugin updates directly from GitHub. Easy, secure, and developer-friendly.

<a href="https://github.com/sponsors/sinanisler">
<img src="https://img.shields.io/badge/Consider_Supporting_My_Projects_❤-GitHub-d46" width="300" height="auto" />
</a>


## Features

- 🔗 **Easy GitHub Integration** - Simply paste your GitHub repository URL
- 🎨 **Theme & Plugin Support** - Sync both WordPress themes and plugins
- 🌿 **Branch Selection** - Choose which branch to sync from
- 🔄 **Update Checking** - Check for new commits before syncing
- 📦 **One-Click Sync** - Download and update with a single click
- 💾 **Automatic Backups** - Creates backups before syncing
- 📊 **Commit Tracking** - View latest commit information
- ⚙️ **Settings Page** - Clean admin interface under Settings menu

## Installation

1. Download or clone this repository
2. Upload the `github-to-wordpress-sync` folder to `/wp-content/plugins/`
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Navigate to **Settings > Github Sync** to configure

## Usage

### Adding a New Project

1. Go to **Settings > Github Sync** in your WordPress admin
2. Enter your GitHub repository URL (e.g., `https://github.com/username/repository-name`)
3. Select the project type (Theme or Plugin)
4. Enter the folder name as it should appear in `wp-content/themes` or `wp-content/plugins`
5. Choose the branch to sync (default is "main")
6. Click **Add Project**

### Syncing a Project

1. Click **Check Update** to see if there are new commits
2. If updates are available, click **Sync Now**
3. Confirm the sync action
4. The plugin will download and update your theme/plugin automatically

### Managing Projects

- **Check Update**: See if there are new commits on GitHub
- **Sync Now**: Download and sync the latest version
- **Delete**: Remove the project from the sync list (doesn't delete files)

## Example

For syncing the theme: `https://github.com/sinanisler/snn-brx-child-theme`

1. **GitHub URL**: `https://github.com/sinanisler/snn-brx-child-theme`
2. **Project Type**: Theme
3. **Project Name**: `snn-brx-child-theme`
4. **Branch**: `main`

## Requirements

- WordPress 5.0 or higher
- PHP 7.2 or higher
- Write permissions on `wp-content/themes` and `wp-content/plugins` directories

## File Structure

```
github-to-wordpress-sync/
├── github-to-wordpress-sync.php    # Main plugin file
├── assets/
│   ├── css/
│   │   └── admin.css               # Admin styles
│   └── js/
│       └── admin.js                # Admin JavaScript
├── includes/
│   ├── admin-page.php              # Settings page template
│   ├── class-github-api.php        # GitHub API integration
│   └── class-sync-manager.php      # Sync functionality
└── README.md                       # Documentation
```

## How It Works

1. **GitHub API**: Fetches repository information, branches, and commit data
2. **Download**: Downloads the repository as a ZIP file from GitHub
3. **Backup**: Creates a timestamped backup of existing files
4. **Extract**: Extracts the ZIP file to a temporary location
5. **Sync**: Copies files from temporary location to target directory
6. **Cleanup**: Removes temporary files

## Security

- All AJAX requests are nonce-protected
- User capability checks (requires `manage_options`)
- Input sanitization and validation
- Safe file operations with WordPress filesystem API

## Limitations

- Public repositories work without authentication
- For private repositories, GitHub API rate limits apply
- Large repositories may take longer to download

## Support

For issues, questions, or contributions, please visit:
[GitHub Repository](https://github.com/sinanisler/github-to-wordpress-sync)

## License

GPL v2 or later

## Author

**Sinan Isler**
- GitHub: [@sinanisler](https://github.com/sinanisler)

## Changelog

### Version 1.0.0
- Initial release
- Add/delete GitHub projects
- Check for updates
- One-click sync
- Branch selection
- Automatic backups
- Commit tracking
- Settings page under Settings menu
