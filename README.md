# Nextcloud welcome widget

ℹ A Markdown rendering Dashboard widget to welcome all users.

⚙ Configure via `Settings > Administration > Theming > Welcome widget`

📄 Pick a Markdown document to be rendered in the widget

💡 If no document is chosen, the widget won't be shown

📝 Edit the Markdown document to update the widget in real-time

🖼 Images are also supported

💬 Configure a contact person to directly start a chat with (requires [Nextcloud Talk](https://apps.nextcloud.com/apps/spreed) to be installed)

### Dashboard layout

Once the app is installed, if you want the Welcome widget to be displayed by default on new users dashboard, change the default dashboard layout:

```
occ config:app:set dashboard layout --value=welcome,recommendations,spreed,mail,calendar
```

### Screenshot

![Welcome widget example](img/screenshot1.jpg)

### General Usage Guide

1. The markdown file should only be created from the Text app for proper support of links and images
2. Manually added links should ideally be absolute links with file IDs for files/folders (eg. `http://cloud.example.com/index.php/f/123`)
3. Images would be displayed only if added through the image icon in the Text app and not through relative path or file ID links
