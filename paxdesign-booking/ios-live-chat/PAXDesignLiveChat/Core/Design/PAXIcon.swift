import SwiftUI

enum PAXIconSize {
    case micro
    case inline
    case row
    case card
    case tab
    case hero
    case menuBar
    case action
    case display

    var length: CGFloat {
        switch self {
        case .micro: return 12
        case .inline: return 16
        case .row: return 20
        case .card, .action: return 22
        case .tab, .menuBar: return 24
        case .hero: return 26
        case .display: return 32
        }
    }
}

enum PAXIconEmphasis {
    case primary, secondary, tertiary, onFill

    var color: Color {
        switch self {
        case .primary: PAXTheme.icon
        case .secondary: PAXTheme.iconSecondary
        case .tertiary: PAXTheme.iconTertiary
        case .onFill: PAXTheme.iconOnFill
        }
    }
}

/// Professional SF Symbol icon, sized like a native iOS app. Template-tinted so Light/Dark always adapt.
struct PAXIcon: View {
    let systemName: String
    var size: PAXIconSize = .row
    var emphasis: PAXIconEmphasis = .primary
    var tint: Color?

    init(_ systemName: String, size: PAXIconSize = .row, emphasis: PAXIconEmphasis = .primary, tint: Color? = nil) {
        self.systemName = systemName
        self.size = size
        self.emphasis = emphasis
        self.tint = tint
    }

    var body: some View {
        Image(systemName: PAXIconCatalog.symbol(for: systemName))
            .font(.system(size: size.length, weight: .semibold))
            .symbolRenderingMode(.hierarchical)
            .foregroundStyle(tint ?? emphasis.color)
            .frame(width: size.length + 2, height: size.length + 2, alignment: .center)
            .accessibilityHidden(true)
    }
}

enum PAXIconCatalog {
    /// Maps legacy custom-asset names and aliases onto official SF Symbols.
    private static let symbols: [String: String] = [
        "dashboard": "house",
        "dashboard.fill": "house.fill",
        "house": "house",
        "house.fill": "house.fill",
        "chats": "bubble.left.and.bubble.right",
        "chats.fill": "bubble.left.and.bubble.right.fill",
        "chat.bubble": "bubble.left",
        "bubble.left": "bubble.left",
        "bubble.left.and.bubble.right": "bubble.left.and.bubble.right",
        "bubble.left.and.bubble.right.fill": "bubble.left.and.bubble.right.fill",
        "message": "message",
        "message.fill": "paperplane.fill",
        "paperplane": "paperplane.fill",
        "paperplane.fill": "paperplane.fill",
        "send": "arrow.up.circle.fill",
        "arrow.up.circle.fill": "arrow.up.circle.fill",
        "team": "person.3",
        "team.fill": "person.3.fill",
        "person.3": "person.3",
        "person.3.fill": "person.3.fill",
        "person.3.sequence": "person.3.sequence",
        "person.3.sequence.fill": "person.3.sequence.fill",
        "person.2": "person.2",
        "person.2.badge.gearshape": "person.2.badge.gearshape",
        "person.2.wave.2": "person.2.wave.2",
        "person.wave.2": "person.wave.2",
        "live": "bell.badge",
        "live.fill": "bell.badge.fill",
        "bell": "bell",
        "bell.fill": "bell.fill",
        "bell.badge": "bell.badge",
        "bell.badge.fill": "bell.badge.fill",
        "bell.and.waves.left.and.right": "bell.and.waves.left.and.right",
        "bell.and.waves.left.and.right.fill": "bell.and.waves.left.and.right.fill",
        "bell.slash": "bell.slash",
        "platform": "square.grid.2x2",
        "platform.fill": "square.grid.2x2.fill",
        "square.grid.2x2": "square.grid.2x2",
        "square.grid.2x2.fill": "square.grid.2x2.fill",
        "profile.user": "person.crop.circle.fill",
        "person": "person",
        "person.fill": "person.fill",
        "person.crop.circle": "person.crop.circle",
        "person.crop.circle.fill": "person.crop.circle.fill",
        "person.crop.circle.badge.checkmark": "person.crop.circle.badge.checkmark",
        "person.crop.circle.badge.clock": "person.crop.circle.badge.clock",
        "person.crop.circle.badge.clock.fill": "person.crop.circle.badge.clock.fill",
        "person.crop.circle.badge.exclamationmark": "person.crop.circle.badge.exclamationmark",
        "person.badge.key": "person.badge.key",
        "person.badge.key.fill": "person.badge.key.fill",
        "person.badge.plus": "person.badge.plus",
        "person.badge.shield.checkmark": "person.badge.shield.checkmark",
        "team.headset": "headphones",
        "headphones": "headphones",
        "headset": "headphones",
        "team.broadcast": "dot.radiowaves.left.and.right",
        "team.alert": "exclamationmark.bubble",
        "gear": "gearshape",
        "gearshape": "gearshape",
        "gearshape.fill": "gearshape.fill",
        "search": "magnifyingglass",
        "magnifyingglass": "magnifyingglass",
        "calendar": "calendar",
        "calendar.badge.clock": "calendar.badge.clock",
        "checklist": "checklist",
        "folder": "folder",
        "folder.fill": "folder.fill",
        "folder.badge.plus": "folder.badge.plus",
        "chart.bar": "chart.bar.fill",
        "chart.bar.doc.horizontal": "chart.bar.doc.horizontal",
        "chart.bar.doc.horizontal.fill": "chart.bar.doc.horizontal.fill",
        "chart.line": "chart.xyaxis.line",
        "chart.xyaxis.line": "chart.xyaxis.line",
        "clock.history": "clock.arrow.circlepath",
        "clock.arrow.circlepath": "clock.arrow.circlepath",
        "employee.badge": "person.crop.circle.badge.checkmark",
        "admin.shield": "shield.lefthalf.filled",
        "shield": "shield",
        "shield.checkered": "checkmark.shield",
        "checkmark.shield": "checkmark.shield",
        "lock.shield": "lock.shield",
        "lock.shield.fill": "lock.shield.fill",
        "lock": "lock",
        "lock.fill": "lock.fill",
        "key": "key",
        "key.fill": "key.fill",
        "device.phone": "iphone",
        "iphone": "iphone",
        "iphone.and.arrow.forward": "iphone.and.arrow.forward",
        "iphone.slash": "iphone.slash",
        "help.bubble": "questionmark.circle",
        "questionmark.circle": "questionmark.circle",
        "questionmark.circle.fill": "questionmark.circle.fill",
        "about.info": "info.circle",
        "info.circle": "info.circle",
        "info.circle.fill": "info.circle.fill",
        "envelope": "envelope",
        "envelope.fill": "envelope.fill",
        "envelope.badge": "envelope.badge",
        "envelope.badge.fill": "envelope.badge.fill",
        "envelope.open": "envelope.open",
        "envelope.open.fill": "envelope.open.fill",
        "doc.text": "doc.text",
        "doc.text.fill": "doc.text.fill",
        "doc.on.doc": "doc.on.doc",
        "doc": "doc",
        "doc.fill": "doc.fill",
        "square.and.arrow.up": "square.and.arrow.up",
        "compose": "square.and.pencil",
        "square.and.pencil": "square.and.pencil",
        "link.chain": "link",
        "link": "link",
        "link.badge.plus": "link.badge.plus",
        "slider.horizontal.3": "slider.horizontal.3",
        "line.3.horizontal": "line.3.horizontal",
        "checkmark.circle": "checkmark.circle",
        "checkmark.circle.fill": "checkmark.circle.fill",
        "checkmark.seal": "checkmark.seal.fill",
        "checkmark.seal.fill": "checkmark.seal.fill",
        "checkmark": "checkmark",
        "xmark": "xmark",
        "xmark.circle": "xmark.circle",
        "xmark.circle.fill": "xmark.circle.fill",
        "xmark.shield": "xmark.shield",
        "xmark.shield.fill": "xmark.shield.fill",
        "chevron.right": "chevron.right",
        "chevron.forward": "chevron.right",
        "chevron.up": "chevron.up",
        "plus": "plus",
        "plus.circle": "plus.circle",
        "plus.circle.fill": "plus.circle.fill",
        "minus.circle": "minus.circle",
        "ellipsis.circle": "ellipsis.circle",
        "trash": "trash",
        "archivebox": "archivebox",
        "eye.slash": "eye.slash",
        "photo": "photo",
        "photo.fill": "photo.fill",
        "photo.on.rectangle": "photo.on.rectangle",
        "photo.on.rectangle.angled": "photo.on.rectangle.angled",
        "photo.circle": "photo.circle",
        "camera": "camera",
        "camera.fill": "camera.fill",
        "globe": "globe",
        "safari": "safari",
        "star": "star",
        "star.fill": "star.fill",
        "crown": "crown",
        "crown.fill": "crown.fill",
        "heart": "heart",
        "heart.fill": "heart.fill",
        "pin": "pin",
        "pin.fill": "pin.fill",
        "pin.slash": "pin.slash",
        "phone": "phone",
        "phone.fill": "phone.fill",
        "phone.down.fill": "phone.down.fill",
        "faceid": "faceid",
        "touchid": "touchid",
        "delete.left": "delete.left",
        "sparkles": "sparkles",
        "paintbrush": "paintbrush",
        "paintbrush.fill": "paintbrush.fill",
        "paintbrush.pointed": "paintbrush.pointed",
        "paintpalette": "paintpalette",
        "paintpalette.fill": "paintpalette.fill",
        "speaker.wave.2": "speaker.wave.2",
        "lifepreserver": "lifepreserver",
        "externaldrive": "externaldrive",
        "hand.raised": "hand.raised",
        "hand.thumbsdown.fill": "hand.thumbsdown.fill",
        "exclamationmark.triangle": "exclamationmark.triangle",
        "exclamationmark.triangle.fill": "exclamationmark.triangle.fill",
        "exclamationmark.shield": "exclamationmark.shield",
        "arrow.up.forward.app": "arrow.up.forward.app",
        "arrow.up.right.circle": "arrow.up.right.circle",
        "arrow.up.right": "arrow.up.right",
        "arrow.down.right": "arrow.down.right",
        "arrow.up.left": "arrow.up.left",
        "arrow.up": "arrow.up",
        "arrow.down": "arrow.down",
        "arrow.down.circle.fill": "arrow.down.circle.fill",
        "arrowshape.turn.up.left": "arrowshape.turn.up.left",
        "number": "number",
        "briefcase": "briefcase",
        "dollarsign.circle": "dollarsign.circle",
        "list.bullet.rectangle": "list.bullet.rectangle",
        "files.stack": "list.bullet.rectangle.portrait",
        "list.bullet.rectangle.portrait": "list.bullet.rectangle.portrait",
        "list.bullet.rectangle.portrait.fill": "list.bullet.rectangle.portrait.fill",
        "mic": "mic",
        "mic.fill": "mic.fill",
        "play": "play.fill",
        "play.fill": "play.fill",
        "play.circle.fill": "play.circle.fill",
        "pause.fill": "pause.fill",
        "waveform": "waveform",
        "location": "location.fill",
        "location.fill": "location.fill",
        "mappin.and.ellipse": "mappin.and.ellipse",
        "megaphone": "megaphone",
        "megaphone.fill": "megaphone.fill",
        "wifi.slash": "wifi.slash",
        "wifi.exclamationmark": "wifi.exclamationmark",
        "code.bracket": "chevron.left.forwardslash.chevron.right",
        "chevron.left.forwardslash.chevron.right": "chevron.left.forwardslash.chevron.right",
        "building.2": "building.2",
        "newspaper": "newspaper",
        "newspaper.fill": "newspaper.fill",
        "tray": "tray",
        "tray.fill": "tray.fill",
        "tray.and.arrow.up": "tray.and.arrow.up",
        "sun.max": "sun.max",
        "sun.max.fill": "sun.max.fill",
        "moon": "moon",
        "moon.fill": "moon.fill",
        "circle.lefthalf.filled": "circle.lefthalf.filled",
        "paperclip": "paperclip",
        "antenna.radiowaves.left.and.right": "antenna.radiowaves.left.and.right",
        "hourglass": "hourglass",
    ]

    static func symbol(for name: String) -> String {
        glyph(for: name)
    }

    static func glyph(for name: String) -> String {
        let lowered = name.lowercased()
        if let mapped = symbols[lowered] { return mapped }
        let stripped = lowered
            .replacingOccurrences(of: ".fill", with: "")
            .replacingOccurrences(of: ".filled", with: "")
        if let mapped = symbols[stripped] { return mapped }
        if lowered.hasPrefix("sf:") {
            return String(lowered.dropFirst(3))
        }
        return lowered.isEmpty ? "circle" : name
    }

    static func quickLinkSymbol(for icon: String, label: String) -> String {
        let raw = icon.hasPrefix("svg:") ? String(icon.dropFirst(4)) : icon
        let sanitized = raw.lowercased()
        if sanitized.hasPrefix("sf:") { return glyph(for: String(sanitized.dropFirst(3))) }
        switch sanitized {
        case "services": return "square.grid.2x2"
        case "projects": return "folder"
        case "pricing": return "dollarsign.circle"
        case "contact": return "envelope"
        case "about": return "info.circle"
        case "faq": return "questionmark.circle"
        case "portfolio": return "photo"
        case "link": return "link"
        default:
            let lower = label.lowercased()
            if lower.contains("service") { return "square.grid.2x2" }
            if lower.contains("project") { return "folder" }
            if lower.contains("pric") { return "dollarsign.circle" }
            if lower.contains("contact") { return "envelope" }
            if lower.contains("about") { return "info.circle" }
            if lower.contains("faq") { return "questionmark.circle" }
            if lower.contains("portfolio") { return "photo" }
            return "link"
        }
    }
}

/// Label with a professional SF Symbol (replaces `Label(..., systemImage:)`).
struct PAXLabel: View {
    let title: String
    let icon: String
    var iconSize: PAXIconSize = .row

    init(_ title: String, icon: String, iconSize: PAXIconSize = .row) {
        self.title = title
        self.icon = icon
        self.iconSize = iconSize
    }

    var body: some View {
        Label { Text(title) } icon: { PAXIcon(icon, size: iconSize) }
    }
}
