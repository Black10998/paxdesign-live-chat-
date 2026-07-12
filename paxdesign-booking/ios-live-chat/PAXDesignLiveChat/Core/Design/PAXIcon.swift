import SwiftUI

enum PAXIconSize: CGFloat {
    case micro = 10
    case inline = 12
    case row = 16
    case card = 18
    case hero = 20
    case tab = 17
    case action = 22
    case display = 26

    var length: CGFloat { rawValue }
    static let strokeWidth: CGFloat = 1.5
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

    init(_ systemName: String, size: PAXIconSize = .row, emphasis: PAXIconEmphasis = .primary) {
        self.systemName = systemName
        self.size = size
        self.emphasis = emphasis
    }

    var body: some View {
        PAXVectorIconShape(name: PAXIconCatalog.outlineSymbol(for: systemName))
            .stroke(
                emphasis.color,
                style: StrokeStyle(lineWidth: size.strokeWidth, lineCap: .round, lineJoin: .round)
            )
            .frame(width: size.length, height: size.length)
            .fixedSize()
            .accessibilityHidden(true)
    }
}

enum PAXIconCatalog {
    private static let explicitMappings: [String: String] = [
        "bell.and.waves.left.and.right.fill": "bell.badge",
        "bubble.left.and.bubble.right.fill": "bubble.left.and.bubble.right",
        "chart.bar.doc.horizontal.fill": "chart.xyaxis.line",
        "checkmark.circle.fill": "checkmark.circle",
        "checkmark.seal.fill": "checkmark.circle",
        "envelope.badge.fill": "envelope.badge",
        "envelope.open.fill": "envelope.open",
        "exclamationmark.triangle.fill": "exclamationmark.triangle",
        "folder.fill": "folder",
        "gearshape.fill": "gearshape",
        "hand.thumbsdown.fill": "hand.thumbsdown",
        "heart.fill": "heart",
        "house.fill": "house",
        "lock.fill": "lock",
        "lock.shield.fill": "lock.shield",
        "message.fill": "bubble.left",
        "paintbrush.pointed.fill": "paintbrush",
        "paintpalette.fill": "paintpalette",
        "paperplane.fill": "paperplane",
        "person.2.fill": "person.2",
        "person.3.fill": "person.3",
        "person.3.sequence.fill": "person.3.sequence",
        "person.badge.key.fill": "person.badge.key",
        "person.crop.circle.badge.clock.fill": "person.crop.circle.badge.clock",
        "person.crop.circle.fill": "person.crop.circle",
        "phone.down.fill": "phone.down",
        "phone.fill": "phone",
        "plus.circle.fill": "plus.circle",
        "shield.lefthalf.filled": "shield",
        "shield.lefthalf.filled.badge.checkmark": "shield.checkered",
        "square.grid.2x2.fill": "square.grid.2x2",
        "star.fill": "star",
        "xmark.circle.fill": "xmark.circle"
    ]

    static func outlineSymbol(for name: String) -> String {
        let lowered = name.lowercased()
        if let mapped = explicitMappings[lowered] { return mapped }
        let stripped = lowered.replacingOccurrences(of: ".fill", with: "").replacingOccurrences(of: ".filled", with: "")
        return stripped.isEmpty ? "circle" : stripped
    }

    static func quickLinkSymbol(for icon: String, label: String) -> String {
        let raw = icon.hasPrefix("svg:") ? String(icon.dropFirst(4)) : icon
        let sanitized = raw.lowercased()
        if sanitized.hasPrefix("sf:") { return outlineSymbol(for: String(sanitized.dropFirst(3))) }
        switch sanitized {
        case "services": return "slider.horizontal.3"
        case "projects": return "square.grid.2x2"
        case "pricing": return "dollarsign.circle"
        case "contact": return "envelope"
        case "about": return "info.circle"
        case "faq": return "questionmark.circle"
        case "portfolio": return "briefcase"
        case "link": return "link"
        default:
            let lower = label.lowercased()
            if lower.contains("service") { return "slider.horizontal.3" }
            if lower.contains("project") { return "square.grid.2x2" }
            if lower.contains("pric") { return "dollarsign.circle" }
            if lower.contains("contact") { return "envelope" }
            if lower.contains("about") { return "info.circle" }
            if lower.contains("faq") { return "questionmark.circle" }
            if lower.contains("portfolio") { return "briefcase" }
            return "link"
        }
    }
}

private struct PAXVectorIconShape: Shape {
    let name: String
    func path(in rect: CGRect) -> Path {
        switch name {
        case "plus", "plus.circle": return plusPath(in: rect, withCircle: name == "plus.circle")
        case "minus.circle": return minusCirclePath(in: rect)
        case "xmark", "xmark.circle": return xmarkPath(in: rect, withCircle: name == "xmark.circle")
        case "checkmark", "checkmark.circle", "checkmark.seal": return checkPath(in: rect, withCircle: name != "checkmark")
        case "chevron.right": return chevronPath(in: rect, direction: .right)
        case "chevron.up": return chevronPath(in: rect, direction: .up)
        case "arrow.up", "arrow.down", "arrow.up.left", "arrow.up.right", "arrow.down.left", "arrow.down.right", "arrow.up.forward.app", "arrow.up.right.circle":
            return arrowPath(in: rect, name: name)
        case "magnifyingglass": return magnifierPath(in: rect)
        case "line.3.horizontal", "slider.horizontal.3": return slidersPath(in: rect)
        case "link", "link.badge.plus": return linkPath(in: rect, addPlus: name == "link.badge.plus")
        case "square.and.pencil": return pencilSquarePath(in: rect)
        case "ellipsis.circle": return ellipsisCirclePath(in: rect)
        case "bell.badge", "bell.and.waves.left.and.right", "bell.fill": return bellPath(in: rect)
        case "bubble.left.and.bubble.right", "bubble.left": return bubblesPath(in: rect)
        case "person", "person.fill", "person.crop.circle", "person.crop.circle.badge.clock", "person.crop.circle.badge.checkmark", "person.badge.key", "person.badge.shield.checkmark":
            return personPath(in: rect)
        case "person.2", "person.3", "person.3.sequence", "person.2.badge.gearshape", "person.2.wave.2", "person.wave.2":
            return peoplePath(in: rect)
        case "lock", "lock.shield": return lockPath(in: rect, withShield: name == "lock.shield")
        case "shield", "shield.checkered", "checkmark.shield", "xmark.shield", "exclamationmark.shield":
            return shieldPath(in: rect)
        case "photo", "photo.on.rectangle", "camera", "photo.circle": return photoPath(in: rect)
        case "envelope", "envelope.badge", "envelope.open": return envelopePath(in: rect)
        case "safari", "globe": return globePath(in: rect)
        case "trash", "archivebox", "eye.slash": return binPath(in: rect)
        case "house", "square.grid.2x2", "list.bullet.rectangle", "list.bullet.rectangle.portrait":
            return modulesPath(in: rect)
        case "calendar", "calendar.badge.clock", "clock", "clock.arrow.circlepath":
            return calendarPath(in: rect)
        case "chart.xyaxis.line", "sparkles", "number", "questionmark.circle", "info.circle", "checklist":
            return insightsPath(in: rect, withCircle: name.hasSuffix(".circle"))
        case "gearshape", "paintbrush", "paintpalette", "speaker.wave.2", "lifepreserver", "externaldrive", "key", "hand.raised":
            return settingsPath(in: rect)
        case "folder", "doc.text", "doc.on.doc", "square.and.arrow.up":
            return filesPath(in: rect)
        case "phone", "phone.down", "iphone", "iphone.slash":
            return devicePath(in: rect)
        case "star", "crown", "pin", "heart", "hand.thumbsdown":
            return badgePath(in: rect)
        case "delete.left":
            return deletePath(in: rect)
        case "lock.open", "faceid", "touchid":
            return lockPath(in: rect, withShield: false)
        default:
            return fallbackPath(in: rect)
        }
    }
    private enum Direction { case up, right }
    private func fallbackPath(in r: CGRect) -> Path { Path(ellipseIn: r.insetBy(dx: r.width * 0.14, dy: r.height * 0.14)) }
    private func plusPath(in r: CGRect, withCircle: Bool) -> Path { var p = Path(); if withCircle { p.addEllipse(in: r.insetBy(dx: r.width*0.12, dy: r.height*0.12)) }; p.move(to: .init(x: r.midX, y: r.minY+r.height*0.24)); p.addLine(to: .init(x: r.midX, y: r.maxY-r.height*0.24)); p.move(to: .init(x: r.minX+r.width*0.24, y: r.midY)); p.addLine(to: .init(x: r.maxX-r.width*0.24, y: r.midY)); return p }
    private func minusCirclePath(in r: CGRect) -> Path { var p = Path(); p.addEllipse(in: r.insetBy(dx: r.width*0.12, dy: r.height*0.12)); p.move(to: .init(x: r.minX+r.width*0.26, y: r.midY)); p.addLine(to: .init(x: r.maxX-r.width*0.26, y: r.midY)); return p }
    private func xmarkPath(in r: CGRect, withCircle: Bool) -> Path { var p = Path(); if withCircle { p.addEllipse(in: r.insetBy(dx: r.width*0.12, dy: r.height*0.12)) }; p.move(to: .init(x: r.minX+r.width*0.26, y: r.minY+r.height*0.26)); p.addLine(to: .init(x: r.maxX-r.width*0.26, y: r.maxY-r.height*0.26)); p.move(to: .init(x: r.maxX-r.width*0.26, y: r.minY+r.height*0.26)); p.addLine(to: .init(x: r.minX+r.width*0.26, y: r.maxY-r.height*0.26)); return p }
    private func checkPath(in r: CGRect, withCircle: Bool) -> Path { var p = Path(); if withCircle { p.addEllipse(in: r.insetBy(dx: r.width*0.12, dy: r.height*0.12)) }; p.move(to: .init(x: r.minX+r.width*0.24, y: r.midY)); p.addLine(to: .init(x: r.midX-r.width*0.02, y: r.maxY-r.height*0.26)); p.addLine(to: .init(x: r.maxX-r.width*0.22, y: r.minY+r.height*0.3)); return p }
    private func chevronPath(in r: CGRect, direction: Direction) -> Path { var p = Path(); if direction == .right { p.move(to: .init(x: r.minX+r.width*0.36, y: r.minY+r.height*0.25)); p.addLine(to: .init(x: r.maxX-r.width*0.34, y: r.midY)); p.addLine(to: .init(x: r.minX+r.width*0.36, y: r.maxY-r.height*0.25)); } else { p.move(to: .init(x: r.minX+r.width*0.25, y: r.maxY-r.height*0.36)); p.addLine(to: .init(x: r.midX, y: r.minY+r.height*0.32)); p.addLine(to: .init(x: r.maxX-r.width*0.25, y: r.maxY-r.height*0.36)); }; return p }
    private func arrowPath(in r: CGRect, name: String) -> Path { var p = Path(); if name.hasSuffix(".circle") { p.addEllipse(in: r.insetBy(dx: r.width*0.12, dy: r.height*0.12)) }; let c = CGPoint(x: r.midX, y: r.midY); let start = CGPoint(x: r.minX+r.width*0.28, y: r.maxY-r.height*0.28); var end = CGPoint(x: r.maxX-r.width*0.26, y: r.minY+r.height*0.28); if name.contains("down") { end = CGPoint(x: r.maxX-r.width*0.26, y: r.maxY-r.height*0.28) }; if name.contains("left") { end.x = r.minX + r.width*0.26 }; p.move(to: start); p.addLine(to: end); p.move(to: end); p.addLine(to: CGPoint(x: end.x - (end.x > c.x ? r.width*0.12 : -r.width*0.12), y: end.y + r.height*0.02)); p.move(to: end); p.addLine(to: CGPoint(x: end.x - (end.x > c.x ? r.width*0.02 : -r.width*0.02), y: end.y + (name.contains("down") ? -r.height*0.12 : r.height*0.12))); return p }
    private func magnifierPath(in r: CGRect) -> Path { var p = Path(); p.addEllipse(in: CGRect(x: r.minX+r.width*0.16, y: r.minY+r.height*0.16, width: r.width*0.52, height: r.height*0.52)); p.move(to: .init(x: r.midX+r.width*0.12, y: r.midY+r.height*0.12)); p.addLine(to: .init(x: r.maxX-r.width*0.15, y: r.maxY-r.height*0.15)); return p }
    private func slidersPath(in r: CGRect) -> Path { var p = Path(); let y:[CGFloat]=[0.28,0.5,0.72]; let x:[CGFloat]=[0.62,0.38,0.56]; for (i,yy) in y.enumerated(){ p.move(to:.init(x:r.minX+r.width*0.18,y:r.minY+r.height*yy)); p.addLine(to:.init(x:r.maxX-r.width*0.18,y:r.minY+r.height*yy)); p.addEllipse(in:CGRect(x:r.minX+r.width*x[i]-r.width*0.08,y:r.minY+r.height*yy-r.height*0.08,width:r.width*0.16,height:r.height*0.16)); } ; return p }
    private func linkPath(in r: CGRect, addPlus: Bool) -> Path { var p = Path(); p.addEllipse(in: CGRect(x:r.minX+r.width*0.14,y:r.minY+r.height*0.34,width:r.width*0.34,height:r.height*0.32)); p.addEllipse(in: CGRect(x:r.minX+r.width*0.52,y:r.minY+r.height*0.34,width:r.width*0.34,height:r.height*0.32)); p.move(to:.init(x:r.midX-r.width*0.06,y:r.midY)); p.addLine(to:.init(x:r.midX+r.width*0.06,y:r.midY)); if addPlus { p.move(to:.init(x:r.maxX-r.width*0.2,y:r.minY+r.height*0.16)); p.addLine(to:.init(x:r.maxX-r.width*0.2,y:r.minY+r.height*0.34)); p.move(to:.init(x:r.maxX-r.width*0.29,y:r.minY+r.height*0.25)); p.addLine(to:.init(x:r.maxX-r.width*0.11,y:r.minY+r.height*0.25)); } ; return p }
    private func pencilSquarePath(in r: CGRect) -> Path { var p = Path(); p.addRoundedRect(in:r.insetBy(dx:r.width*0.14,dy:r.height*0.14), cornerSize:.init(width:r.width*0.12,height:r.height*0.12)); p.move(to:.init(x:r.midX,y:r.maxY-r.height*0.24)); p.addLine(to:.init(x:r.maxX-r.width*0.16,y:r.minY+r.height*0.22)); return p }
    private func ellipsisCirclePath(in r: CGRect) -> Path { var p = Path(); p.addEllipse(in:r.insetBy(dx:r.width*0.12,dy:r.height*0.12)); for i in 0..<3 { p.addEllipse(in:CGRect(x:r.minX+r.width*(0.32+CGFloat(i)*0.16), y:r.midY-r.height*0.05, width:r.width*0.1, height:r.height*0.1)); } ; return p }
    private func bellPath(in r: CGRect) -> Path { var p = Path(); p.move(to:.init(x:r.minX+r.width*0.28,y:r.maxY-r.height*0.28)); p.addQuadCurve(to:.init(x:r.maxX-r.width*0.28,y:r.maxY-r.height*0.28), control:.init(x:r.midX,y:r.maxY-r.height*0.16)); p.addLine(to:.init(x:r.maxX-r.width*0.34,y:r.minY+r.height*0.42)); p.addQuadCurve(to:.init(x:r.minX+r.width*0.34,y:r.minY+r.height*0.42), control:.init(x:r.midX,y:r.minY+r.height*0.18)); p.closeSubpath(); p.addEllipse(in:CGRect(x:r.midX-r.width*0.06,y:r.maxY-r.height*0.22,width:r.width*0.12,height:r.height*0.1)); return p }
    private func bubblesPath(in r: CGRect) -> Path { var p = Path(); p.addRoundedRect(in:CGRect(x:r.minX+r.width*0.12,y:r.minY+r.height*0.2,width:r.width*0.5,height:r.height*0.44), cornerSize:.init(width:r.width*0.12,height:r.height*0.12)); p.move(to:.init(x:r.minX+r.width*0.34,y:r.maxY-r.height*0.36)); p.addLine(to:.init(x:r.minX+r.width*0.28,y:r.maxY-r.height*0.18)); p.addRoundedRect(in:CGRect(x:r.minX+r.width*0.42,y:r.minY+r.height*0.36,width:r.width*0.46,height:r.height*0.38), cornerSize:.init(width:r.width*0.11,height:r.height*0.11)); return p }
    private func personPath(in r: CGRect) -> Path { var p=Path(); p.addEllipse(in:CGRect(x:r.midX-r.width*0.16,y:r.minY+r.height*0.16,width:r.width*0.32,height:r.height*0.28)); p.addRoundedRect(in:CGRect(x:r.midX-r.width*0.28,y:r.minY+r.height*0.48,width:r.width*0.56,height:r.height*0.34), cornerSize:.init(width:r.width*0.2,height:r.height*0.2)); return p }
    private func peoplePath(in r: CGRect) -> Path { var p=Path(); p.addEllipse(in:CGRect(x:r.minX+r.width*0.18,y:r.minY+r.height*0.22,width:r.width*0.24,height:r.height*0.22)); p.addEllipse(in:CGRect(x:r.minX+r.width*0.58,y:r.minY+r.height*0.22,width:r.width*0.24,height:r.height*0.22)); p.addEllipse(in:CGRect(x:r.minX+r.width*0.38,y:r.minY+r.height*0.14,width:r.width*0.24,height:r.height*0.24)); p.addRoundedRect(in:CGRect(x:r.minX+r.width*0.18,y:r.minY+r.height*0.5,width:r.width*0.64,height:r.height*0.28), cornerSize:.init(width:r.width*0.16,height:r.height*0.16)); return p }
    private func lockPath(in r: CGRect, withShield: Bool) -> Path { var p=Path(); p.addRoundedRect(in:CGRect(x:r.minX+r.width*0.24,y:r.minY+r.height*0.45,width:r.width*0.52,height:r.height*0.38), cornerSize:.init(width:r.width*0.1,height:r.height*0.1)); p.move(to:.init(x:r.minX+r.width*0.34,y:r.minY+r.height*0.46)); p.addQuadCurve(to:.init(x:r.maxX-r.width*0.34,y:r.minY+r.height*0.46), control:.init(x:r.midX,y:r.minY+r.height*0.2)); if withShield { p.addRoundedRect(in:CGRect(x:r.minX+r.width*0.1,y:r.minY+r.height*0.12,width:r.width*0.2,height:r.height*0.24), cornerSize:.init(width:r.width*0.04,height:r.height*0.04)); } ; return p }
    private func shieldPath(in r: CGRect) -> Path { var p=Path(); p.move(to:.init(x:r.midX,y:r.minY+r.height*0.12)); p.addLine(to:.init(x:r.maxX-r.width*0.2,y:r.minY+r.height*0.22)); p.addLine(to:.init(x:r.maxX-r.width*0.24,y:r.maxY-r.height*0.24)); p.addLine(to:.init(x:r.midX,y:r.maxY-r.height*0.12)); p.addLine(to:.init(x:r.minX+r.width*0.24,y:r.maxY-r.height*0.24)); p.addLine(to:.init(x:r.minX+r.width*0.2,y:r.minY+r.height*0.22)); p.closeSubpath(); return p }
    private func photoPath(in r: CGRect) -> Path { var p=Path(); p.addRoundedRect(in:r.insetBy(dx:r.width*0.12,dy:r.height*0.18), cornerSize:.init(width:r.width*0.08,height:r.height*0.08)); p.addEllipse(in:CGRect(x:r.minX+r.width*0.24,y:r.minY+r.height*0.26,width:r.width*0.14,height:r.height*0.14)); p.move(to:.init(x:r.minX+r.width*0.2,y:r.maxY-r.height*0.26)); p.addLine(to:.init(x:r.midX-r.width*0.04,y:r.midY+r.height*0.08)); p.addLine(to:.init(x:r.maxX-r.width*0.2,y:r.maxY-r.height*0.26)); return p }
    private func envelopePath(in r: CGRect) -> Path { var p=Path(); p.addRoundedRect(in:r.insetBy(dx:r.width*0.14,dy:r.height*0.22), cornerSize:.init(width:r.width*0.08,height:r.height*0.08)); p.move(to:.init(x:r.minX+r.width*0.16,y:r.minY+r.height*0.26)); p.addLine(to:.init(x:r.midX,y:r.midY)); p.addLine(to:.init(x:r.maxX-r.width*0.16,y:r.minY+r.height*0.26)); return p }
    private func globePath(in r: CGRect) -> Path { var p=Path(); let c=r.insetBy(dx:r.width*0.14,dy:r.height*0.14); p.addEllipse(in:c); p.move(to:.init(x:c.midX,y:c.minY)); p.addLine(to:.init(x:c.midX,y:c.maxY)); p.move(to:.init(x:c.minX,y:c.midY)); p.addLine(to:.init(x:c.maxX,y:c.midY)); p.addEllipse(in:CGRect(x:c.minX+c.width*0.2,y:c.minY,width:c.width*0.6,height:c.height)); return p }
    private func binPath(in r: CGRect) -> Path { var p=Path(); p.addRoundedRect(in:CGRect(x:r.minX+r.width*0.28,y:r.minY+r.height*0.28,width:r.width*0.44,height:r.height*0.54), cornerSize:.init(width:r.width*0.06,height:r.height*0.06)); p.move(to:.init(x:r.minX+r.width*0.24,y:r.minY+r.height*0.28)); p.addLine(to:.init(x:r.maxX-r.width*0.24,y:r.minY+r.height*0.28)); p.move(to:.init(x:r.minX+r.width*0.4,y:r.minY+r.height*0.2)); p.addLine(to:.init(x:r.maxX-r.width*0.4,y:r.minY+r.height*0.2)); return p }
    private func modulesPath(in r: CGRect) -> Path { var p=Path(); p.addRoundedRect(in:r.insetBy(dx:r.width*0.14,dy:r.height*0.14), cornerSize:.init(width:r.width*0.08,height:r.height*0.08)); p.move(to:.init(x:r.midX,y:r.minY+r.height*0.16)); p.addLine(to:.init(x:r.midX,y:r.maxY-r.height*0.16)); p.move(to:.init(x:r.minX+r.width*0.16,y:r.midY)); p.addLine(to:.init(x:r.maxX-r.width*0.16,y:r.midY)); return p }
    private func calendarPath(in r: CGRect) -> Path { var p=Path(); p.addRoundedRect(in:r.insetBy(dx:r.width*0.14,dy:r.height*0.16), cornerSize:.init(width:r.width*0.1,height:r.height*0.1)); p.move(to:.init(x:r.minX+r.width*0.14,y:r.minY+r.height*0.34)); p.addLine(to:.init(x:r.maxX-r.width*0.14,y:r.minY+r.height*0.34)); p.move(to:.init(x:r.minX+r.width*0.3,y:r.minY+r.height*0.14)); p.addLine(to:.init(x:r.minX+r.width*0.3,y:r.minY+r.height*0.26)); p.move(to:.init(x:r.maxX-r.width*0.3,y:r.minY+r.height*0.14)); p.addLine(to:.init(x:r.maxX-r.width*0.3,y:r.minY+r.height*0.26)); return p }
    private func insightsPath(in r: CGRect, withCircle: Bool) -> Path { var p=Path(); if withCircle { p.addEllipse(in:r.insetBy(dx:r.width*0.12,dy:r.height*0.12)) }; p.move(to:.init(x:r.minX+r.width*0.2,y:r.maxY-r.height*0.24)); p.addLine(to:.init(x:r.minX+r.width*0.4,y:r.midY)); p.addLine(to:.init(x:r.midX,y:r.maxY-r.height*0.34)); p.addLine(to:.init(x:r.maxX-r.width*0.2,y:r.minY+r.height*0.28)); return p }
    private func settingsPath(in r: CGRect) -> Path { var p=Path(); p.addEllipse(in:r.insetBy(dx:r.width*0.18,dy:r.height*0.18)); p.addEllipse(in:r.insetBy(dx:r.width*0.36,dy:r.height*0.36)); return p }
    private func filesPath(in r: CGRect) -> Path { var p=Path(); p.addRoundedRect(in:r.insetBy(dx:r.width*0.16,dy:r.height*0.14), cornerSize:.init(width:r.width*0.08,height:r.height*0.08)); p.move(to:.init(x:r.minX+r.width*0.3,y:r.minY+r.height*0.34)); p.addLine(to:.init(x:r.maxX-r.width*0.3,y:r.minY+r.height*0.34)); p.move(to:.init(x:r.minX+r.width*0.3,y:r.midY)); p.addLine(to:.init(x:r.maxX-r.width*0.3,y:r.midY)); return p }
    private func devicePath(in r: CGRect) -> Path { var p=Path(); p.addRoundedRect(in:r.insetBy(dx:r.width*0.28,dy:r.height*0.1), cornerSize:.init(width:r.width*0.16,height:r.height*0.16)); p.addEllipse(in:CGRect(x:r.midX-r.width*0.04,y:r.maxY-r.height*0.2,width:r.width*0.08,height:r.height*0.08)); if name == "iphone.slash" { p.move(to:.init(x:r.minX+r.width*0.2,y:r.maxY-r.height*0.18)); p.addLine(to:.init(x:r.maxX-r.width*0.2,y:r.minY+r.height*0.18)); } ; return p }
    private func badgePath(in r: CGRect) -> Path { var p=Path(); if name == "pin" { p.move(to:.init(x:r.midX,y:r.minY+r.height*0.14)); p.addLine(to:.init(x:r.midX,y:r.maxY-r.height*0.14)); p.addEllipse(in:CGRect(x:r.midX-r.width*0.14,y:r.minY+r.height*0.14,width:r.width*0.28,height:r.height*0.28)); } else if name == "heart" || name == "hand.thumbsdown" { p.addEllipse(in:r.insetBy(dx:r.width*0.18,dy:r.height*0.2)); } else { p.addEllipse(in:r.insetBy(dx:r.width*0.16,dy:r.height*0.16)); p.move(to:.init(x:r.minX+r.width*0.28,y:r.midY)); p.addLine(to:.init(x:r.maxX-r.width*0.28,y:r.midY)); }; return p }
    private func deletePath(in r: CGRect) -> Path { var p=Path(); p.addRoundedRect(in:r.insetBy(dx:r.width*0.12,dy:r.height*0.26), cornerSize:.init(width:r.width*0.08,height:r.height*0.08)); p.move(to:.init(x:r.maxX-r.width*0.22,y:r.minY+r.height*0.26)); p.addLine(to:.init(x:r.maxX-r.width*0.38,y:r.midY)); p.addLine(to:.init(x:r.maxX-r.width*0.22,y:r.maxY-r.height*0.26)); return p }
}
