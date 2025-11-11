# WP MUX Plugin Documentation

This directory contains all documentation for the WP MUX plugin.

## 📁 Directory Structure

### `/reference/` - Core Reference Documentation
Technical documentation for developers working on or extending the plugin:
- **REFACTORING_QUICK_REFERENCE.md** - API reference for utility classes (Wp_Mux_Utils, Wp_Mux_Validator, Wp_Mux_Queue_Manager, etc.)
- **THEME_SYSTEM.md** - Player theme system documentation
- **REFACTORING_ANALYSIS.md** - Plugin architecture analysis and design decisions

### `/features/` - Feature Documentation
Comprehensive guides for major plugin features:
- **ANALYTICS_QUICK_START.md** - Quick start guide for video analytics feature
- **MUX_ANALYTICS_FEATURE.md** - Detailed analytics integration documentation
- **POSTER_QUICK_START.md** - Quick start guide for poster/thumbnail generation
- **MUX_POSTER_INTEGRATION.md** - Detailed poster feature documentation
- **MUX_POSTER_FEATURE_SUMMARY.md** - Implementation summary for poster feature
- **BULK_MIGRATION.md** - Bulk video migration feature guide

### `/archive/` - Historical Documentation
Bug fixes, implementation notes, and completed features - kept for historical reference but not actively maintained.

## 🚀 Getting Started

### For Users
Start with the quick start guides in `/features/`:
1. **POSTER_QUICK_START.md** - Learn how to customize video thumbnails
2. **ANALYTICS_QUICK_START.md** - View video statistics in WordPress
3. **BULK_MIGRATION.md** - Migrate existing videos to Mux

### For Developers
Check the reference documentation in `/reference/`:
1. **REFACTORING_QUICK_REFERENCE.md** - Essential API reference
2. **THEME_SYSTEM.md** - Understand the theme system
3. **Feature docs** - Deep dive into specific features

## 📚 Key Documentation

### Plugin Architecture
- **Utility Classes**: `Wp_Mux_Utils` - AJAX helpers, caching, validation
- **API Integration**: `Wp_Mux_Api` - Mux API client with caching
- **Queue System**: `Wp_Mux_Queue_Manager` - Background video processing
- **Theme Management**: `Wp_Mux_Theme_Manager` - Player theme system
- **Validation**: `Wp_Mux_Validator` - Data validation utilities

### Major Features
1. **Video Upload & Processing** - Automatic upload to Mux with queue management
2. **Analytics Integration** - View counts, watch time, quality metrics
3. **Poster Generation** - Custom thumbnails and animated GIFs from Mux
4. **Theme System** - Customizable player controls and appearance
5. **Bulk Migration** - Migrate existing video libraries to Mux

## 🔧 Development Guidelines

### When to Update Documentation
- **Adding new features** → Create/update documentation in `/features/`
- **Changing APIs** → Update `/reference/REFACTORING_QUICK_REFERENCE.md`
- **Fixing bugs** → Consider if feature docs need updates (don't create new fix docs)
- **Refactoring** → Update architecture docs in `/reference/`

### Documentation Standards
- Use markdown with clear headings
- Include code examples for APIs
- Add "Last Updated" dates for major changes
- Keep quick start guides concise (<5 min read)
- Keep detailed guides comprehensive with examples

## 📝 Contributing

When adding new documentation:
1. Place it in the appropriate directory (`reference/`, `features/`, or root)
2. Update this README if adding new categories
3. Use clear, descriptive filenames
4. Follow existing documentation style

---

**Last Updated**: November 11, 2025
**Plugin Version**: 1.3.0-dev
