### Displaying Like Avatars

⚠️ We are currently investigating issues with syncing Likes; the Likes Collage is currently non-functional. ⚠️

**Using the Block Editor**:

1. Add the "Social Likes Collage" block to your post content
2. Configure the block settings in the sidebar:
   - Select a specific platform or show all
   - Set the maximum number of users to display
   - Adjust the avatar size
   - Toggle the total count display

**Using a Shortcode**:

You can also use the `[social_bridge_likes]` shortcode with the following attributes:

```
[social_bridge_likes platform="" max_users="8" avatar_size="48" show_total="true" class=""]
```

- `platform`: Specific platform ID (leave empty for all platforms)
- `max_users`: Maximum number of users to display (default: 8)
- `avatar_size`: Size of avatars in pixels (default: 48)
- `show_total`: Whether to show the total count (default: true)
- `class`: Additional CSS class for styling