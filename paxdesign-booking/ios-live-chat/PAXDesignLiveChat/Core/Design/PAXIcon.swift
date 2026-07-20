import SwiftUI

enum PAXIconSize: CGFloat {
    case micro = 11
    case inline = 14
    case row = 18
    case card = 20
    case tab = 19
    case hero = 22
    case menuBar = 22.4
    case action = 24
    case display = 28

    var length: CGFloat { rawValue }
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

/// Premium SVG asset icon from the PAXIcons catalog. Do not apply `.font()` — use `size:` instead.
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

    private var assetName: String {
        PAXIconCatalog.glyph(for: systemName)
    }

    var body: some View {
        Image(assetName)
            .renderingMode(.template)
            .resizable()
            .scaledToFit()
            .frame(width: size.length, height: size.length)
            .foregroundStyle(tint ?? emphasis.color)
            .fixedSize()
            .accessibilityHidden(true)
    }
}

enum PAXIconCatalog {
    private static let explicitMappings: [String: String] = [
        "house": "dashboard",
        "house.fill": "dashboard.fill",
        "chart.bar.doc.horizontal.fill": "chart.bar",
        "chart.bar.doc.horizontal": "chart.bar",
        "bubble.left.and.bubble.right": "chats",
        "bubble.left.and.bubble.right.fill": "chats.fill",
        "bubble.left": "chat.bubble",
        "message.fill": "paperplane",
        "person.3": "team",
        "person.3.fill": "team.fill",
        "person.3.sequence": "person.3.sequence",
        "person.3.sequence.fill": "person.3.sequence.fill",
        "person.2": "person.2",
        "person.2.badge.gearshape": "person.2.badge.gearshape",
        "person.2.wave.2": "person.2.wave.2",
        "person.wave.2": "person.wave.2",
        "bell.and.waves.left.and.right": "live",
        "bell.and.waves.left.and.right.fill": "live.fill",
        "bell.badge": "notification",
        "bell.badge.fill": "notification",
        "bell.fill": "live",
        "square.grid.2x2": "platform",
        "square.grid.2x2.fill": "platform.fill",
        "arrow.up.circle.fill": "send",
        "paperplane.fill": "paperplane",
        "calendar": "calendar",
        "calendar.badge.clock": "calendar.badge.clock",
        "checklist": "checklist",
        "folder.fill": "folder",
        "folder": "folder",
        "chart.xyaxis.line": "chart.line",
        "clock.arrow.circlepath": "clock.history",
        "person.crop.circle.badge.checkmark": "employee.badge",
        "person.crop.circle.fill": "profile.user",
        "person.crop.circle": "profile.user",
        "person.badge.key.fill": "employee.badge",
        "person.badge.plus": "person.badge.key",
        "key.fill": "key",
        "shield.lefthalf.filled": "admin.shield",
        "shield.lefthalf.filled.badge.checkmark": "shield.checkered",
        "shield": "admin.shield",
        "lock.shield": "lock.shield",
        "iphone.and.arrow.forward": "device.phone",
        "iphone": "iphone",
        "gearshape": "gear",
        "gearshape.fill": "gear",
        "questionmark.circle.fill": "help.bubble",
        "info.circle.fill": "about.info",
        "envelope.badge.fill": "envelope.badge",
        "envelope.open.fill": "envelope.open",
        "envelope.open": "envelope.open",
        "envelope": "envelope",
        "doc.text": "doc.text",
        "doc.on.doc": "doc.on.doc",
        "square.and.arrow.up": "square.and.arrow.up",
        "magnifyingglass": "search",
        "square.and.pencil": "compose",
        "link.badge.plus": "link.badge.plus",
        "link": "link.chain",
        "slider.horizontal.3": "slider.horizontal.3",
        "line.3.horizontal": "line.3.horizontal",
        "checkmark.circle.fill": "checkmark.circle",
        "checkmark.circle": "checkmark.circle",
        "checkmark.seal.fill": "checkmark.seal",
        "xmark.circle.fill": "xmark.circle",
        "xmark.circle": "xmark.circle",
        "chevron.right": "chevron.right",
        "chevron.up": "chevron.up",
        "plus.circle": "plus.circle",
        "plus": "plus",
        "minus.circle": "minus.circle",
        "ellipsis.circle": "ellipsis.circle",
        "trash": "trash",
        "archivebox": "archivebox",
        "eye.slash": "eye.slash",
        "photo.on.rectangle": "photo.on.rectangle",
        "camera": "camera",
        "photo": "photo",
        "globe": "globe",
        "safari": "safari",
        "star": "star",
        "star.fill": "star",
        "crown": "crown",
        "crown.fill": "crown",
        "heart": "heart",
        "pin.fill": "pin",
        "pin.slash": "pin.slash",
        "bell.slash": "bell.slash",
        "heart.fill": "heart",
        "phone.fill": "phone",
        "phone.down.fill": "phone.down",
        "lock.fill": "lock",
        "lock": "lock",
        "faceid": "faceid",
        "touchid": "touchid",
        "delete.left": "delete.left",
        "sparkles": "sparkles",
        "paintbrush": "paintbrush",
        "paintbrush.fill": "paintbrush",
        "paintpalette": "paintpalette",
        "speaker.wave.2": "speaker.wave.2",
        "lifepreserver": "lifepreserver",
        "externaldrive": "externaldrive",
        "key": "key",
        "hand.raised": "hand.raised",
        "hand.thumbsdown.fill": "hand.thumbsdown",
        "exclamationmark.triangle.fill": "exclamationmark.triangle",
        "exclamationmark.shield": "exclamationmark.shield",
        "checkmark.shield": "checkmark.shield",
        "person.crop.circle.badge.clock.fill": "person.crop.circle.badge.clock",
        "person.crop.circle.badge.clock": "person.crop.circle.badge.clock",
        "person.badge.shield.checkmark": "person.badge.shield.checkmark",
        "person.badge.key": "person.badge.key",
        "person": "profile.user",
        "person.fill": "profile.user",
        "arrow.up.forward.app": "arrow.up.forward.app",
        "arrow.up.right.circle": "arrow.up.right.circle",
        "arrow.up.right": "arrow.up.right",
        "arrow.down.right": "arrow.down.right",
        "arrow.up.left": "arrow.up.left",
        "arrow.up": "arrow.up",
        "arrow.down": "arrow.down",
        "number": "number",
        "questionmark.circle": "questionmark.circle",
        "info.circle": "about.info",
        "briefcase": "briefcase",
        "dollarsign.circle": "dollarsign.circle",
        "list.bullet.rectangle": "list.bullet.rectangle",
        "list.bullet.rectangle.portrait": "files.stack",
        "list.bullet.rectangle.portrait.fill": "files.stack",
        "circle": "plus.circle",
        "iphone.slash": "iphone.slash",
        "hourglass": "clock.history",
        "pin": "pin",
        "mic": "mic",
        "mic.fill": "mic",
        "play.fill": "play",
        "pause.fill": "pause",
        "waveform": "waveform",
        "location.fill": "location",
        "headphones": "team.headset",
        "headset": "team.headset",
        "team.headset": "team.headset",
        "team.broadcast": "team.broadcast",
        "team.alert": "team.alert",
        "megaphone": "megaphone",
        "megaphone.fill": "megaphone",
        "arrow.triangle.turn.up.right.circle.fill": "arrow.up.right.circle",
        "wifi.slash": "wifi.slash",
        "wifi.exclamationmark": "wifi.exclamationmark",
        "chevron.left.forwardslash.chevron.right": "code.bracket",
        "building.2": "building.2",
        "folder.badge.plus": "folder.badge.plus",
        "photo.on.rectangle.angled": "photo.on.rectangle",
        "plus.circle.fill": "plus.circle",
        "paintpalette.fill": "paintpalette",
        "mappin.and.ellipse": "location",
        "play.circle.fill": "play",
        "message": "chat.bubble",
        "newspaper": "newspaper",
        "newspaper.fill": "newspaper",
        "tray": "tray",
        "tray.fill": "tray",
        "doc": "doc.text",
        "doc.fill": "doc.text",
        "doc.text.fill": "doc.text",
        "camera.fill": "camera",
        "person.crop.circle.badge.exclamationmark": "person.crop.circle.badge.clock",
        "sun.max": "sun.max",
        "sun.max.fill": "sun.max",
        "moon": "moon",
        "moon.fill": "moon",
        "circle.lefthalf.filled": "circle.lefthalf.filled",
        "circle.lefthalf.filled.fill": "circle.lefthalf.filled",
        "xmark": "xmark",
        "xmark.shield": "xmark.shield",
        "xmark.shield.fill": "xmark.shield",
        "arrowshape.turn.up.left": "arrowshape.turn.up.left",
        "paintbrush.pointed": "paintbrush.pointed",
        "photo.circle": "photo.circle",
        "tray.and.arrow.up": "tray.and.arrow.up",
        "checkmark": "checkmark",
        "send": "send",
        "paperplane": "paperplane",
        "exclamationmark.triangle": "exclamationmark.triangle",
        "antenna.radiowaves.left.and.right": "speaker.wave.2",
        "paperclip": "paperclip",
        "location": "location",
        "arrow.down.circle.fill": "arrow.down",
        "chevron.forward": "chevron.right",
        "lock.shield.fill": "lock.shield",
        "envelope.fill": "envelope",
        "bell": "live"
    ]

    static func glyph(for name: String) -> String {
        let lowered = name.lowercased()
        if let mapped = explicitMappings[lowered] { return mapped }
        let stripped = lowered
            .replacingOccurrences(of: ".fill", with: "")
            .replacingOccurrences(of: ".filled", with: "")
        if let mapped = explicitMappings[stripped] { return mapped }
        return stripped.isEmpty ? "plus.circle" : stripped
    }

    static func quickLinkSymbol(for icon: String, label: String) -> String {
        let raw = icon.hasPrefix("svg:") ? String(icon.dropFirst(4)) : icon
        let sanitized = raw.lowercased()
        if sanitized.hasPrefix("sf:") { return glyph(for: String(sanitized.dropFirst(3))) }
        switch sanitized {
        case "services": return "slider.horizontal.3"
        case "projects": return "platform"
        case "pricing": return "dollarsign.circle"
        case "contact": return "envelope"
        case "about": return "about.info"
        case "faq": return "help.bubble"
        case "portfolio": return "briefcase"
        case "link": return "link.chain"
        default:
            let lower = label.lowercased()
            if lower.contains("service") { return "slider.horizontal.3" }
            if lower.contains("project") { return "platform" }
            if lower.contains("pric") { return "dollarsign.circle" }
            if lower.contains("contact") { return "envelope" }
            if lower.contains("about") { return "about.info" }
            if lower.contains("faq") { return "help.bubble" }
            if lower.contains("portfolio") { return "briefcase" }
            return "link.chain"
        }
    }
}

/// Label with PAX SVG icon (replaces `Label(..., systemImage:)`).
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
