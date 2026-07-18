import SwiftUI

enum PAXIconSize: CGFloat {
    case micro = 11
    case inline = 14
    case row = 18
    case card = 20
    case hero = 22
    case tab = 19
    case action = 24
    case display = 28

    var length: CGFloat { rawValue }
    static let strokeWidth: CGFloat = 1.55
    var strokeWidth: CGFloat { Self.strokeWidth }
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

/// Fixed-size monochrome vector icon. Do not apply `.font()` — use `size:` instead.
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
        PAXVectorIconShape(name: PAXIconCatalog.glyph(for: systemName))
            .stroke(
                tint ?? emphasis.color,
                style: StrokeStyle(lineWidth: size.strokeWidth, lineCap: .round, lineJoin: .round)
            )
            .frame(width: size.length, height: size.length)
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
        "calendar.badge.clock": "calendar",
        "checklist": "checklist",
        "folder.fill": "folder",
        "folder": "folder",
        "chart.xyaxis.line": "chart.line",
        "clock.arrow.circlepath": "clock.history",
        "person.crop.circle.badge.checkmark": "employee.badge",
        "person.crop.circle.fill": "profile.user",
        "person.crop.circle": "profile.user",
        "person.badge.key.fill": "employee.badge",
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
        "star.fill": "star",
        "crown.fill": "crown",
        "pin.fill": "pin",
        "pin.slash": "pin",
        "bell.slash": "eye.slash",
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
        "list.bullet.rectangle": "checklist",
        "list.bullet.rectangle.portrait": "files.stack",
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
        "megaphone": "team.broadcast",
        "megaphone.fill": "team.broadcast",
        "arrow.triangle.turn.up.right.circle.fill": "arrow.up.right.circle"
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

private struct PAXVectorIconShape: Shape {
    let name: String

    func path(in rect: CGRect) -> Path {
        PAXGlyphPaths.path(for: name, in: rect)
    }
}
