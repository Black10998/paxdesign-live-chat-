import SwiftUI

enum PAXGlyphPaths {
    static func path(for glyph: String, in rect: CGRect) -> Path {
        switch glyph {
        case "dashboard", "dashboard.fill": return dashboardPath(in: rect, filled: glyph.hasSuffix(".fill"))
        case "chats", "chats.fill": return chatsPath(in: rect, filled: glyph.hasSuffix(".fill"))
        case "team", "team.fill": return teamPath(in: rect, filled: glyph.hasSuffix(".fill"))
        case "live", "live.fill": return livePath(in: rect, filled: glyph.hasSuffix(".fill"))
        case "platform", "platform.fill": return platformPath(in: rect, filled: glyph.hasSuffix(".fill"))
        case "send": return sendPath(in: rect)
        case "calendar": return calendarPath(in: rect)
        case "checklist": return checklistPath(in: rect)
        case "folder": return folderPath(in: rect)
        case "chart.line": return chartLinePath(in: rect)
        case "chart.bar": return chartBarPath(in: rect)
        case "clock.history": return clockHistoryPath(in: rect)
        case "employee.badge": return employeeBadgePath(in: rect)
        case "admin.shield": return adminShieldPath(in: rect)
        case "device.phone": return devicePhonePath(in: rect)
        case "gear": return gearPath(in: rect)
        case "notification": return notificationPath(in: rect)
        case "files.stack": return filesStackPath(in: rect)
        case "help.bubble": return helpBubblePath(in: rect)
        case "about.info": return aboutInfoPath(in: rect)
        case "profile.user": return profileUserPath(in: rect)
        case "chat.bubble": return singleChatBubblePath(in: rect)
        case "link.chain", "link.badge.plus": return linkChainPath(in: rect, plus: glyph.contains("plus"))
        case "search": return searchPath(in: rect)
        case "compose": return composePath(in: rect)
        case "plus": return plusPath(in: rect, circle: false)
        case "plus.circle": return plusPath(in: rect, circle: true)
        case "minus.circle": return minusCirclePath(in: rect)
        case "xmark", "xmark.circle": return xmarkPath(in: rect, circle: glyph.contains("circle"))
        case "checkmark", "checkmark.circle": return checkPath(in: rect, circle: glyph.contains("circle"))
        case "chevron.right": return chevronPath(in: rect)
        case "chevron.up": return chevronUpPath(in: rect)
        case "arrow.up", "arrow.down", "arrow.up.right", "arrow.up.left", "arrow.down.right", "arrow.up.forward.app", "arrow.up.right.circle", "arrow.up.circle.fill":
            return arrowPath(in: rect, name: glyph)
        case "lock", "lock.shield", "lock.fill": return lockPath(in: rect, shield: glyph.contains("shield"))
        case "shield", "shield.checkered": return shieldPath(in: rect, checkered: glyph.contains("checkered"))
        case "envelope", "envelope.badge", "envelope.open": return envelopePath(in: rect, variant: glyph)
        case "photo", "camera", "photo.on.rectangle": return photoPath(in: rect, camera: glyph == "camera")
        case "trash", "archivebox", "eye.slash": return trashPath(in: rect)
        case "globe", "safari": return globePath(in: rect)
        case "star", "crown", "pin", "heart": return accentBadgePath(in: rect, kind: glyph)
        case "phone", "phone.down", "phone.fill": return phonePath(in: rect, down: glyph.contains("down"))
        case "iphone", "iphone.slash": return iphonePath(in: rect, slash: glyph.contains("slash"))
        case "ellipsis.circle": return ellipsisCirclePath(in: rect)
        case "delete.left": return deleteLeftPath(in: rect)
        case "faceid", "touchid": return biometricsPath(in: rect)
        case "paintbrush", "paintpalette", "speaker.wave.2", "lifepreserver", "externaldrive", "key", "hand.raised":
            return utilityPath(in: rect, kind: glyph)
        case "doc.text", "doc.on.doc", "square.and.arrow.up": return documentPath(in: rect, kind: glyph)
        case "person.wave.2", "person.2.wave.2": return teamWavePath(in: rect)
        case "person.2", "person.2.badge.gearshape": return pairPeoplePath(in: rect)
        case "person.3.sequence", "person.3.sequence.fill": return teamSequencePath(in: rect)
        case "sparkles": return sparklesPath(in: rect)
        case "paperplane", "paperplane.fill", "message.fill", "bubble.left": return paperplanePath(in: rect)
        case "hand.thumbsdown": return thumbsPath(in: rect)
        case "slider.horizontal.3", "line.3.horizontal": return slidersPath(in: rect)
        case "square.and.pencil": return composePath(in: rect)
        case "exclamationmark.triangle", "exclamationmark.shield": return warningPath(in: rect)
        case "checkmark.shield", "checkmark.seal", "checkmark.seal.fill": return verifiedPath(in: rect)
        case "person.crop.circle.badge.clock", "person.crop.circle.badge.checkmark": return profileBadgePath(in: rect, clock: glyph.contains("clock"))
        case "bell.badge", "bell.and.waves.left.and.right": return livePath(in: rect, filled: false)
        case "number", "questionmark.circle", "info.circle": return infoGlyphPath(in: rect, kind: glyph)
        case "briefcase", "dollarsign.circle": return businessPath(in: rect, kind: glyph)
        case "mic", "mic.fill": return micPath(in: rect)
        case "play", "play.fill": return playPath(in: rect)
        case "pause", "pause.fill": return pausePath(in: rect)
        case "waveform": return waveformPath(in: rect)
        case "location", "location.fill": return locationPath(in: rect)
        case "arrow.up.right.circle": return arrowPath(in: rect, name: "arrow.up.right.circle")
        default: return fallbackPath(in: rect)
        }
    }

    private static func fallbackPath(in r: CGRect) -> Path {
        Path(ellipseIn: r.insetBy(dx: r.width * 0.18, dy: r.height * 0.18))
    }

    private static func dashboardPath(in r: CGRect, filled: Bool) -> Path {
        var p = Path()
        let frame = r.insetBy(dx: r.width * 0.12, dy: r.height * 0.14)
        p.addRoundedRect(in: frame, cornerSize: .init(width: r.width * 0.08, height: r.height * 0.08))
        let bars: [CGFloat] = [0.42, 0.72, 0.56, 0.84]
        for (i, h) in bars.enumerated() {
            let w = r.width * 0.1
            let x = frame.minX + r.width * (0.18 + CGFloat(i) * 0.16)
            let bar = CGRect(x: x, y: frame.maxY - r.height * h * 0.62, width: w, height: r.height * h * 0.5)
            if filled { p.addRoundedRect(in: bar, cornerSize: .init(width: 1.5, height: 1.5)) }
            else {
                p.addRoundedRect(in: bar, cornerSize: .init(width: 1.5, height: 1.5))
            }
        }
        p.move(to: CGPoint(x: frame.minX + r.width * 0.16, y: frame.minY + r.height * 0.22))
        p.addLine(to: CGPoint(x: frame.maxX - r.width * 0.16, y: frame.minY + r.height * 0.22))
        return p
    }

    private static func chatsPath(in r: CGRect, filled: Bool) -> Path {
        var p = Path()
        let bubble = CGRect(x: r.minX + r.width * 0.1, y: r.minY + r.height * 0.14, width: r.width * 0.56, height: r.height * 0.5)
        p.addRoundedRect(in: bubble, cornerSize: .init(width: r.width * 0.14, height: r.height * 0.14))
        p.move(to: CGPoint(x: bubble.minX + r.width * 0.14, y: bubble.maxY))
        p.addLine(to: CGPoint(x: bubble.minX + r.width * 0.08, y: r.maxY - r.height * 0.12))
        p.addLine(to: CGPoint(x: bubble.minX + r.width * 0.24, y: bubble.maxY - r.height * 0.02))
        let reply = CGRect(x: r.minX + r.width * 0.36, y: r.minY + r.height * 0.4, width: r.width * 0.5, height: r.height * 0.38)
        p.addRoundedRect(in: reply, cornerSize: .init(width: r.width * 0.12, height: r.height * 0.12))
        if filled {
            p.move(to: CGPoint(x: bubble.minX + r.width * 0.2, y: bubble.midY - r.height * 0.02))
            p.addLine(to: CGPoint(x: bubble.maxX - r.width * 0.14, y: bubble.midY - r.height * 0.02))
            p.move(to: CGPoint(x: bubble.minX + r.width * 0.2, y: bubble.midY + r.height * 0.1))
            p.addLine(to: CGPoint(x: bubble.maxX - r.width * 0.22, y: bubble.midY + r.height * 0.1))
        }
        return p
    }

    private static func teamPath(in r: CGRect, filled: Bool) -> Path {
        var p = Path()
        let heads: [(CGFloat, CGFloat)] = [(0.22, 0.2), (0.58, 0.2), (0.4, 0.1)]
        for (x, y) in heads {
            p.addEllipse(in: CGRect(x: r.minX + r.width * x, y: r.minY + r.height * y, width: r.width * 0.2, height: r.height * 0.18))
        }
        p.addRoundedRect(in: CGRect(x: r.minX + r.width * 0.16, y: r.minY + r.height * 0.46, width: r.width * 0.68, height: r.height * 0.24), cornerSize: .init(width: r.width * 0.1, height: r.height * 0.1))
        p.move(to: CGPoint(x: r.midX - r.width * 0.18, y: r.minY + r.height * 0.08))
        p.addQuadCurve(to: CGPoint(x: r.midX + r.width * 0.18, y: r.minY + r.height * 0.08), control: CGPoint(x: r.midX, y: r.minY - r.height * 0.04))
        if filled {
            p.move(to: CGPoint(x: r.midX, y: r.minY + r.height * 0.52))
            p.addLine(to: CGPoint(x: r.midX, y: r.minY + r.height * 0.64))
        }
        return p
    }

    private static func livePath(in r: CGRect, filled: Bool) -> Path {
        var p = Path()
        p.move(to: CGPoint(x: r.minX + r.width * 0.3, y: r.maxY - r.height * 0.28))
        p.addQuadCurve(to: CGPoint(x: r.maxX - r.width * 0.3, y: r.maxY - r.height * 0.28), control: CGPoint(x: r.midX, y: r.maxY - r.height * 0.14))
        p.addLine(to: CGPoint(x: r.maxX - r.width * 0.34, y: r.minY + r.height * 0.4))
        p.addQuadCurve(to: CGPoint(x: r.minX + r.width * 0.34, y: r.minY + r.height * 0.4), control: CGPoint(x: r.midX, y: r.minY + r.height * 0.16))
        p.closeSubpath()
        p.addEllipse(in: CGRect(x: r.midX - r.width * 0.05, y: r.maxY - r.height * 0.2, width: r.width * 0.1, height: r.height * 0.08))
        p.move(to: CGPoint(x: r.minX + r.width * 0.08, y: r.midY))
        p.addQuadCurve(to: CGPoint(x: r.minX + r.width * 0.02, y: r.midY - r.height * 0.12), control: CGPoint(x: r.minX + r.width * 0.02, y: r.midY))
        p.move(to: CGPoint(x: r.maxX - r.width * 0.08, y: r.midY))
        p.addQuadCurve(to: CGPoint(x: r.maxX - r.width * 0.02, y: r.midY - r.height * 0.12), control: CGPoint(x: r.maxX - r.width * 0.02, y: r.midY))
        if filled {
            p.move(to: CGPoint(x: r.midX, y: r.minY + r.height * 0.44))
            p.addLine(to: CGPoint(x: r.midX, y: r.minY + r.height * 0.56))
        }
        return p
    }

    private static func platformPath(in r: CGRect, filled: Bool) -> Path {
        var p = Path()
        let c = CGPoint(x: r.midX, y: r.midY)
        let radius = r.width * 0.28
        for i in 0..<6 {
            let angle = CGFloat(i) * .pi / 3 - .pi / 2
            let point = CGPoint(x: c.x + cos(angle) * radius, y: c.y + sin(angle) * radius)
            if i == 0 { p.move(to: point) } else { p.addLine(to: point) }
        }
        p.closeSubpath()
        p.addEllipse(in: CGRect(x: c.x - r.width * 0.08, y: c.y - r.height * 0.08, width: r.width * 0.16, height: r.height * 0.16))
        let nodes: [CGPoint] = [
            CGPoint(x: r.minX + r.width * 0.18, y: r.minY + r.height * 0.22),
            CGPoint(x: r.maxX - r.width * 0.18, y: r.minY + r.height * 0.22),
            CGPoint(x: r.minX + r.width * 0.18, y: r.maxY - r.height * 0.22),
            CGPoint(x: r.maxX - r.width * 0.18, y: r.maxY - r.height * 0.22)
        ]
        for node in nodes {
            p.addEllipse(in: CGRect(x: node.x - r.width * 0.05, y: node.y - r.height * 0.05, width: r.width * 0.1, height: r.height * 0.1))
            p.move(to: c)
            p.addLine(to: node)
        }
        if filled {
            p.addEllipse(in: CGRect(x: c.x - r.width * 0.04, y: c.y - r.height * 0.04, width: r.width * 0.08, height: r.height * 0.08))
        }
        return p
    }

    static func sendPath(in r: CGRect) -> Path {
        var p = Path()
        p.addEllipse(in: r.insetBy(dx: r.width * 0.08, dy: r.height * 0.08))
        p.move(to: CGPoint(x: r.minX + r.width * 0.34, y: r.maxY - r.height * 0.34))
        p.addLine(to: CGPoint(x: r.maxX - r.width * 0.28, y: r.minY + r.height * 0.3))
        p.addLine(to: CGPoint(x: r.minX + r.width * 0.42, y: r.minY + r.height * 0.42))
        p.closeSubpath()
        p.move(to: CGPoint(x: r.minX + r.width * 0.34, y: r.maxY - r.height * 0.34))
        p.addLine(to: CGPoint(x: r.minX + r.width * 0.42, y: r.minY + r.height * 0.42))
        return p
    }

    private static func calendarPath(in r: CGRect) -> Path {
        var p = Path()
        let body = r.insetBy(dx: r.width * 0.14, dy: r.height * 0.16)
        p.addRoundedRect(in: body, cornerSize: .init(width: r.width * 0.08, height: r.height * 0.08))
        p.move(to: CGPoint(x: body.minX, y: body.minY + r.height * 0.16))
        p.addLine(to: CGPoint(x: body.maxX, y: body.minY + r.height * 0.16))
        p.addRect(CGRect(x: body.minX + r.width * 0.12, y: body.minY - r.height * 0.04, width: r.width * 0.06, height: r.height * 0.1))
        p.addRect(CGRect(x: body.maxX - r.width * 0.18, y: body.minY - r.height * 0.04, width: r.width * 0.06, height: r.height * 0.1))
        for row in 0..<2 {
            for col in 0..<3 {
                p.addEllipse(in: CGRect(x: body.minX + r.width * (0.14 + CGFloat(col) * 0.18), y: body.minY + r.height * (0.28 + CGFloat(row) * 0.18), width: r.width * 0.06, height: r.height * 0.06))
            }
        }
        return p
    }

    private static func checklistPath(in r: CGRect) -> Path {
        var p = Path()
        let rows: [CGFloat] = [0.24, 0.48, 0.72]
        for y in rows {
            p.addRoundedRect(in: CGRect(x: r.minX + r.width * 0.12, y: r.minY + r.height * y - r.height * 0.06, width: r.width * 0.12, height: r.height * 0.12), cornerSize: .init(width: 2, height: 2))
            p.move(to: CGPoint(x: r.minX + r.width * 0.3, y: r.minY + r.height * y))
            p.addLine(to: CGPoint(x: r.maxX - r.width * 0.14, y: r.minY + r.height * y))
        }
        p.move(to: CGPoint(x: r.minX + r.width * 0.16, y: r.minY + r.height * 0.24))
        p.addLine(to: CGPoint(x: r.minX + r.width * 0.2, y: r.minY + r.height * 0.28))
        p.addLine(to: CGPoint(x: r.minX + r.width * 0.24, y: r.minY + r.height * 0.2))
        return p
    }

    private static func folderPath(in r: CGRect) -> Path {
        var p = Path()
        p.move(to: CGPoint(x: r.minX + r.width * 0.14, y: r.minY + r.height * 0.34))
        p.addLine(to: CGPoint(x: r.minX + r.width * 0.34, y: r.minY + r.height * 0.34))
        p.addLine(to: CGPoint(x: r.minX + r.width * 0.42, y: r.minY + r.height * 0.24))
        p.addLine(to: CGPoint(x: r.maxX - r.width * 0.14, y: r.minY + r.height * 0.24))
        p.addLine(to: CGPoint(x: r.maxX - r.width * 0.14, y: r.maxY - r.height * 0.18))
        p.addLine(to: CGPoint(x: r.minX + r.width * 0.14, y: r.maxY - r.height * 0.18))
        p.closeSubpath()
        return p
    }

    private static func chartLinePath(in r: CGRect) -> Path {
        var p = Path()
        p.move(to: CGPoint(x: r.minX + r.width * 0.14, y: r.maxY - r.height * 0.18))
        p.addLine(to: CGPoint(x: r.minX + r.width * 0.14, y: r.minY + r.height * 0.22))
        p.addLine(to: CGPoint(x: r.maxX - r.width * 0.12, y: r.minY + r.height * 0.22))
        p.move(to: CGPoint(x: r.minX + r.width * 0.22, y: r.maxY - r.height * 0.34))
        p.addLine(to: CGPoint(x: r.minX + r.width * 0.42, y: r.minY + r.height * 0.46))
        p.addLine(to: CGPoint(x: r.minX + r.width * 0.58, y: r.minY + r.height * 0.38))
        p.addLine(to: CGPoint(x: r.maxX - r.width * 0.18, y: r.minY + r.height * 0.52))
        return p
    }

    private static func chartBarPath(in r: CGRect) -> Path {
        var p = Path()
        let bars: [(CGFloat, CGFloat)] = [(0.18, 0.5), (0.38, 0.72), (0.58, 0.44), (0.78, 0.64)]
        for (x, h) in bars {
            p.addRoundedRect(in: CGRect(x: r.minX + r.width * x, y: r.maxY - r.height * h, width: r.width * 0.12, height: r.height * (h - 0.18)), cornerSize: .init(width: 2, height: 2))
        }
        p.move(to: CGPoint(x: r.minX + r.width * 0.12, y: r.maxY - r.height * 0.16))
        p.addLine(to: CGPoint(x: r.maxX - r.width * 0.12, y: r.maxY - r.height * 0.16))
        return p
    }

    private static func clockHistoryPath(in r: CGRect) -> Path {
        var p = Path()
        let c = r.insetBy(dx: r.width * 0.16, dy: r.height * 0.16)
        p.addEllipse(in: c)
        p.move(to: CGPoint(x: c.midX, y: c.midY))
        p.addLine(to: CGPoint(x: c.midX, y: c.minY + c.height * 0.3))
        p.move(to: CGPoint(x: c.midX, y: c.midY))
        p.addLine(to: CGPoint(x: c.midX + c.width * 0.18, y: c.midY + c.height * 0.08))
        p.addArc(center: CGPoint(x: c.maxX + r.width * 0.02, y: c.minY + r.height * 0.08), radius: r.width * 0.14, startAngle: .degrees(200), endAngle: .degrees(-20), clockwise: false)
        p.move(to: CGPoint(x: c.maxX + r.width * 0.1, y: c.minY + r.height * 0.02))
        p.addLine(to: CGPoint(x: c.maxX + r.width * 0.16, y: c.minY - r.height * 0.02))
        p.move(to: CGPoint(x: c.maxX + r.width * 0.1, y: c.minY + r.height * 0.02))
        p.addLine(to: CGPoint(x: c.maxX + r.width * 0.04, y: c.minY - r.height * 0.02))
        return p
    }

    private static func employeeBadgePath(in r: CGRect) -> Path {
        var p = Path()
        p.addEllipse(in: CGRect(x: r.midX - r.width * 0.14, y: r.minY + r.height * 0.12, width: r.width * 0.28, height: r.height * 0.24))
        p.addRoundedRect(in: CGRect(x: r.midX - r.width * 0.22, y: r.minY + r.height * 0.4, width: r.width * 0.44, height: r.height * 0.34), cornerSize: .init(width: r.width * 0.12, height: r.height * 0.12))
        p.addRoundedRect(in: CGRect(x: r.maxX - r.width * 0.28, y: r.minY + r.height * 0.18, width: r.width * 0.18, height: r.height * 0.22), cornerSize: .init(width: 3, height: 3))
        p.move(to: CGPoint(x: r.maxX - r.width * 0.24, y: r.minY + r.height * 0.26))
        p.addLine(to: CGPoint(x: r.maxX - r.width * 0.14, y: r.minY + r.height * 0.32))
        p.addLine(to: CGPoint(x: r.maxX - r.width * 0.2, y: r.minY + r.height * 0.34))
        return p
    }

    private static func adminShieldPath(in r: CGRect) -> Path {
        var p = Path()
        p.move(to: CGPoint(x: r.midX, y: r.minY + r.height * 0.1))
        p.addLine(to: CGPoint(x: r.maxX - r.width * 0.18, y: r.minY + r.height * 0.2))
        p.addLine(to: CGPoint(x: r.maxX - r.width * 0.22, y: r.maxY - r.height * 0.22))
        p.addLine(to: CGPoint(x: r.midX, y: r.maxY - r.height * 0.1))
        p.addLine(to: CGPoint(x: r.minX + r.width * 0.22, y: r.maxY - r.height * 0.22))
        p.addLine(to: CGPoint(x: r.minX + r.width * 0.18, y: r.minY + r.height * 0.2))
        p.closeSubpath()
        p.move(to: CGPoint(x: r.midX, y: r.minY + r.height * 0.28))
        p.addLine(to: CGPoint(x: r.midX, y: r.maxY - r.height * 0.28))
        p.move(to: CGPoint(x: r.minX + r.width * 0.34, y: r.midY))
        p.addLine(to: CGPoint(x: r.maxX - r.width * 0.34, y: r.midY))
        return p
    }

    private static func devicePhonePath(in r: CGRect) -> Path {
        var p = Path()
        p.addRoundedRect(in: r.insetBy(dx: r.width * 0.3, dy: r.height * 0.1), cornerSize: .init(width: r.width * 0.08, height: r.height * 0.08))
        p.move(to: CGPoint(x: r.maxX - r.width * 0.18, y: r.midY))
        p.addLine(to: CGPoint(x: r.maxX - r.width * 0.04, y: r.midY - r.height * 0.08))
        p.addLine(to: CGPoint(x: r.maxX - r.width * 0.04, y: r.midY + r.height * 0.08))
        p.closeSubpath()
        return p
    }

    private static func gearPath(in r: CGRect) -> Path {
        var p = Path()
        let outer = r.insetBy(dx: r.width * 0.16, dy: r.height * 0.16)
        for i in 0..<8 {
            let angle = CGFloat(i) * .pi / 4
            let inner = CGPoint(x: r.midX + cos(angle) * outer.width * 0.34, y: r.midY + sin(angle) * outer.height * 0.34)
            let outerPt = CGPoint(x: r.midX + cos(angle) * outer.width * 0.5, y: r.midY + sin(angle) * outer.height * 0.5)
            if i == 0 { p.move(to: outerPt) } else { p.addLine(to: outerPt) }
            let nextAngle = angle + .pi / 8
            p.addLine(to: CGPoint(x: r.midX + cos(nextAngle) * outer.width * 0.38, y: r.midY + sin(nextAngle) * outer.height * 0.38))
            _ = inner
        }
        p.closeSubpath()
        p.addEllipse(in: r.insetBy(dx: r.width * 0.34, dy: r.height * 0.34))
        return p
    }

    private static func notificationPath(in r: CGRect) -> Path {
        var p = livePath(in: r, filled: false)
        p.addEllipse(in: CGRect(x: r.maxX - r.width * 0.24, y: r.minY + r.height * 0.14, width: r.width * 0.1, height: r.height * 0.1))
        return p
    }

    private static func filesStackPath(in r: CGRect) -> Path {
        var p = Path()
        p.addRoundedRect(in: CGRect(x: r.minX + r.width * 0.22, y: r.minY + r.height * 0.14, width: r.width * 0.56, height: r.height * 0.62), cornerSize: .init(width: 4, height: 4))
        p.addRoundedRect(in: CGRect(x: r.minX + r.width * 0.14, y: r.minY + r.height * 0.22, width: r.width * 0.56, height: r.height * 0.62), cornerSize: .init(width: 4, height: 4))
        p.move(to: CGPoint(x: r.minX + r.width * 0.28, y: r.minY + r.height * 0.38))
        p.addLine(to: CGPoint(x: r.maxX - r.width * 0.22, y: r.minY + r.height * 0.38))
        p.move(to: CGPoint(x: r.minX + r.width * 0.28, y: r.minY + r.height * 0.5))
        p.addLine(to: CGPoint(x: r.maxX - r.width * 0.3, y: r.minY + r.height * 0.5))
        return p
    }

    private static func helpBubblePath(in r: CGRect) -> Path {
        var p = Path()
        p.addRoundedRect(in: r.insetBy(dx: r.width * 0.14, dy: r.height * 0.18), cornerSize: .init(width: r.width * 0.16, height: r.height * 0.16))
        p.move(to: CGPoint(x: r.midX - r.width * 0.04, y: r.maxY - r.height * 0.16))
        p.addLine(to: CGPoint(x: r.midX - r.width * 0.1, y: r.maxY - r.height * 0.04))
        p.addLine(to: CGPoint(x: r.midX + r.width * 0.04, y: r.maxY - r.height * 0.16))
        p.move(to: CGPoint(x: r.midX, y: r.minY + r.height * 0.3))
        p.addCurve(to: CGPoint(x: r.midX, y: r.minY + r.height * 0.52), control1: CGPoint(x: r.midX - r.width * 0.12, y: r.minY + r.height * 0.34), control2: CGPoint(x: r.midX - r.width * 0.12, y: r.minY + r.height * 0.48))
        p.move(to: CGPoint(x: r.midX, y: r.minY + r.height * 0.6))
        p.addEllipse(in: CGRect(x: r.midX - r.width * 0.03, y: r.minY + r.height * 0.58, width: r.width * 0.06, height: r.height * 0.06))
        return p
    }

    private static func aboutInfoPath(in r: CGRect) -> Path {
        var p = Path()
        p.addEllipse(in: r.insetBy(dx: r.width * 0.14, dy: r.height * 0.14))
        p.move(to: CGPoint(x: r.midX, y: r.minY + r.height * 0.58))
        p.addLine(to: CGPoint(x: r.midX, y: r.minY + r.height * 0.72))
        p.move(to: CGPoint(x: r.midX, y: r.minY + r.height * 0.34))
        p.addEllipse(in: CGRect(x: r.midX - r.width * 0.04, y: r.minY + r.height * 0.28, width: r.width * 0.08, height: r.height * 0.08))
        return p
    }

    private static func profileUserPath(in r: CGRect) -> Path {
        var p = Path()
        p.addEllipse(in: r.insetBy(dx: r.width * 0.14, dy: r.height * 0.14))
        p.addEllipse(in: CGRect(x: r.midX - r.width * 0.12, y: r.minY + r.height * 0.24, width: r.width * 0.24, height: r.height * 0.22))
        p.addRoundedRect(in: CGRect(x: r.midX - r.width * 0.2, y: r.minY + r.height * 0.5, width: r.width * 0.4, height: r.height * 0.24), cornerSize: .init(width: r.width * 0.12, height: r.height * 0.12))
        return p
    }

    private static func singleChatBubblePath(in r: CGRect) -> Path {
        var p = Path()
        let bubble = r.insetBy(dx: r.width * 0.14, dy: r.height * 0.2)
        p.addRoundedRect(in: bubble, cornerSize: .init(width: r.width * 0.14, height: r.height * 0.14))
        p.move(to: CGPoint(x: bubble.minX + r.width * 0.12, y: bubble.maxY))
        p.addLine(to: CGPoint(x: bubble.minX + r.width * 0.04, y: r.maxY - r.height * 0.12))
        p.addLine(to: CGPoint(x: bubble.minX + r.width * 0.2, y: bubble.maxY - r.height * 0.02))
        p.move(to: CGPoint(x: bubble.minX + r.width * 0.22, y: bubble.midY))
        p.addLine(to: CGPoint(x: bubble.maxX - r.width * 0.22, y: bubble.midY))
        return p
    }

    private static func linkChainPath(in r: CGRect, plus: Bool = false) -> Path {
        var p = Path()
        p.addEllipse(in: CGRect(x: r.minX + r.width * 0.12, y: r.minY + r.height * 0.34, width: r.width * 0.34, height: r.height * 0.32))
        p.addEllipse(in: CGRect(x: r.minX + r.width * 0.54, y: r.minY + r.height * 0.34, width: r.width * 0.34, height: r.height * 0.32))
        if plus {
            p.move(to: CGPoint(x: r.maxX - r.width * 0.2, y: r.minY + r.height * 0.16))
            p.addLine(to: CGPoint(x: r.maxX - r.width * 0.2, y: r.minY + r.height * 0.34))
            p.move(to: CGPoint(x: r.maxX - r.width * 0.28, y: r.minY + r.height * 0.25))
            p.addLine(to: CGPoint(x: r.maxX - r.width * 0.12, y: r.minY + r.height * 0.25))
        }
        return p
    }

    private static func searchPath(in r: CGRect) -> Path {
        var p = Path()
        p.addEllipse(in: CGRect(x: r.minX + r.width * 0.14, y: r.minY + r.height * 0.14, width: r.width * 0.52, height: r.height * 0.52))
        p.move(to: CGPoint(x: r.midX + r.width * 0.1, y: r.midY + r.height * 0.1))
        p.addLine(to: CGPoint(x: r.maxX - r.width * 0.14, y: r.maxY - r.height * 0.14))
        return p
    }

    private static func composePath(in r: CGRect) -> Path {
        var p = Path()
        p.addRoundedRect(in: r.insetBy(dx: r.width * 0.14, dy: r.height * 0.14), cornerSize: .init(width: r.width * 0.08, height: r.height * 0.08))
        p.move(to: CGPoint(x: r.midX + r.width * 0.04, y: r.minY + r.height * 0.24))
        p.addLine(to: CGPoint(x: r.maxX - r.width * 0.16, y: r.minY + r.height * 0.22))
        p.move(to: CGPoint(x: r.maxX - r.width * 0.16, y: r.minY + r.height * 0.22))
        p.addLine(to: CGPoint(x: r.maxX - r.width * 0.18, y: r.minY + r.height * 0.34))
        return p
    }

    private static func plusPath(in r: CGRect, circle: Bool) -> Path {
        var p = Path()
        if circle { p.addEllipse(in: r.insetBy(dx: r.width * 0.12, dy: r.height * 0.12)) }
        p.move(to: CGPoint(x: r.midX, y: r.minY + r.height * 0.24))
        p.addLine(to: CGPoint(x: r.midX, y: r.maxY - r.height * 0.24))
        p.move(to: CGPoint(x: r.minX + r.width * 0.24, y: r.midY))
        p.addLine(to: CGPoint(x: r.maxX - r.width * 0.24, y: r.midY))
        return p
    }

    private static func minusCirclePath(in r: CGRect) -> Path {
        var p = Path()
        p.addEllipse(in: r.insetBy(dx: r.width * 0.12, dy: r.height * 0.12))
        p.move(to: CGPoint(x: r.minX + r.width * 0.28, y: r.midY))
        p.addLine(to: CGPoint(x: r.maxX - r.width * 0.28, y: r.midY))
        return p
    }

    private static func xmarkPath(in r: CGRect, circle: Bool) -> Path {
        var p = Path()
        if circle { p.addEllipse(in: r.insetBy(dx: r.width * 0.12, dy: r.height * 0.12)) }
        p.move(to: CGPoint(x: r.minX + r.width * 0.28, y: r.minY + r.height * 0.28))
        p.addLine(to: CGPoint(x: r.maxX - r.width * 0.28, y: r.maxY - r.height * 0.28))
        p.move(to: CGPoint(x: r.maxX - r.width * 0.28, y: r.minY + r.height * 0.28))
        p.addLine(to: CGPoint(x: r.minX + r.width * 0.28, y: r.maxY - r.height * 0.28))
        return p
    }

    private static func checkPath(in r: CGRect, circle: Bool) -> Path {
        var p = Path()
        if circle { p.addEllipse(in: r.insetBy(dx: r.width * 0.12, dy: r.height * 0.12)) }
        p.move(to: CGPoint(x: r.minX + r.width * 0.24, y: r.midY))
        p.addLine(to: CGPoint(x: r.midX - r.width * 0.02, y: r.maxY - r.height * 0.28))
        p.addLine(to: CGPoint(x: r.maxX - r.width * 0.22, y: r.minY + r.height * 0.3))
        return p
    }

    private static func chevronPath(in r: CGRect) -> Path {
        var p = Path()
        p.move(to: CGPoint(x: r.minX + r.width * 0.36, y: r.minY + r.height * 0.26))
        p.addLine(to: CGPoint(x: r.maxX - r.width * 0.34, y: r.midY))
        p.addLine(to: CGPoint(x: r.minX + r.width * 0.36, y: r.maxY - r.height * 0.26))
        return p
    }

    private static func chevronUpPath(in r: CGRect) -> Path {
        var p = Path()
        p.move(to: CGPoint(x: r.minX + r.width * 0.26, y: r.maxY - r.height * 0.36))
        p.addLine(to: CGPoint(x: r.midX, y: r.minY + r.height * 0.32))
        p.addLine(to: CGPoint(x: r.maxX - r.width * 0.26, y: r.maxY - r.height * 0.36))
        return p
    }

    private static func arrowPath(in r: CGRect, name: String) -> Path {
        if name == "arrow.up.circle.fill" || name.contains("circle") {
            return sendPath(in: r)
        }
        var p = Path()
        var start = CGPoint(x: r.minX + r.width * 0.28, y: r.maxY - r.height * 0.28)
        var end = CGPoint(x: r.maxX - r.width * 0.26, y: r.minY + r.height * 0.28)
        if name.contains("down") { end.y = r.maxY - r.height * 0.28 }
        if name.contains("left") {
            start = CGPoint(x: r.maxX - r.width * 0.28, y: r.maxY - r.height * 0.28)
            end = CGPoint(x: r.minX + r.width * 0.26, y: r.minY + r.height * 0.28)
        }
        p.move(to: start)
        p.addLine(to: end)
        return p
    }

    private static func lockPath(in r: CGRect, shield: Bool) -> Path {
        var p = Path()
        p.addRoundedRect(in: CGRect(x: r.minX + r.width * 0.26, y: r.minY + r.height * 0.46, width: r.width * 0.48, height: r.height * 0.36), cornerSize: .init(width: 4, height: 4))
        p.move(to: CGPoint(x: r.minX + r.width * 0.34, y: r.minY + r.height * 0.46))
        p.addQuadCurve(to: CGPoint(x: r.maxX - r.width * 0.34, y: r.minY + r.height * 0.46), control: CGPoint(x: r.midX, y: r.minY + r.height * 0.22))
        if shield {
            p.addRoundedRect(in: CGRect(x: r.minX + r.width * 0.12, y: r.minY + r.height * 0.14, width: r.width * 0.2, height: r.height * 0.24), cornerSize: .init(width: 3, height: 3))
        }
        return p
    }

    private static func shieldPath(in r: CGRect, checkered: Bool) -> Path {
        var p = adminShieldPath(in: r)
        if checkered {
            p.move(to: CGPoint(x: r.minX + r.width * 0.34, y: r.minY + r.height * 0.34))
            p.addLine(to: CGPoint(x: r.maxX - r.width * 0.34, y: r.maxY - r.height * 0.34))
            p.move(to: CGPoint(x: r.maxX - r.width * 0.34, y: r.minY + r.height * 0.34))
            p.addLine(to: CGPoint(x: r.minX + r.width * 0.34, y: r.maxY - r.height * 0.34))
        }
        return p
    }

    private static func envelopePath(in r: CGRect, variant: String) -> Path {
        var p = Path()
        let body = r.insetBy(dx: r.width * 0.14, dy: r.height * 0.24)
        p.addRoundedRect(in: body, cornerSize: .init(width: 4, height: 4))
        p.move(to: CGPoint(x: body.minX + r.width * 0.04, y: body.minY + r.height * 0.04))
        p.addLine(to: CGPoint(x: r.midX, y: r.midY))
        p.addLine(to: CGPoint(x: body.maxX - r.width * 0.04, y: body.minY + r.height * 0.04))
        if variant.contains("badge") {
            p.addEllipse(in: CGRect(x: body.maxX - r.width * 0.08, y: body.minY - r.height * 0.04, width: r.width * 0.1, height: r.height * 0.1))
        }
        return p
    }

    private static func photoPath(in r: CGRect, camera: Bool) -> Path {
        var p = Path()
        if camera {
            p.addRoundedRect(in: r.insetBy(dx: r.width * 0.16, dy: r.height * 0.22), cornerSize: .init(width: 4, height: 4))
            p.addEllipse(in: CGRect(x: r.midX - r.width * 0.12, y: r.midY - r.height * 0.04, width: r.width * 0.24, height: r.height * 0.24))
        } else {
            p.addRoundedRect(in: r.insetBy(dx: r.width * 0.12, dy: r.height * 0.18), cornerSize: .init(width: 4, height: 4))
            p.addEllipse(in: CGRect(x: r.minX + r.width * 0.22, y: r.minY + r.height * 0.26, width: r.width * 0.12, height: r.height * 0.12))
        }
        return p
    }

    private static func trashPath(in r: CGRect) -> Path {
        var p = Path()
        p.addRoundedRect(in: CGRect(x: r.minX + r.width * 0.3, y: r.minY + r.height * 0.3, width: r.width * 0.4, height: r.height * 0.52), cornerSize: .init(width: 4, height: 4))
        p.move(to: CGPoint(x: r.minX + r.width * 0.26, y: r.minY + r.height * 0.3))
        p.addLine(to: CGPoint(x: r.maxX - r.width * 0.26, y: r.minY + r.height * 0.3))
        return p
    }

    private static func globePath(in r: CGRect) -> Path {
        var p = Path()
        let c = r.insetBy(dx: r.width * 0.14, dy: r.height * 0.14)
        p.addEllipse(in: c)
        p.move(to: CGPoint(x: c.midX, y: c.minY))
        p.addLine(to: CGPoint(x: c.midX, y: c.maxY))
        p.addEllipse(in: CGRect(x: c.minX + c.width * 0.18, y: c.minY, width: c.width * 0.64, height: c.height))
        return p
    }

    private static func accentBadgePath(in r: CGRect, kind: String) -> Path {
        var p = Path()
        switch kind {
        case "pin":
            p.move(to: CGPoint(x: r.midX, y: r.minY + r.height * 0.14))
            p.addLine(to: CGPoint(x: r.midX, y: r.maxY - r.height * 0.16))
            p.addEllipse(in: CGRect(x: r.midX - r.width * 0.14, y: r.minY + r.height * 0.14, width: r.width * 0.28, height: r.height * 0.28))
        case "crown":
            p.move(to: CGPoint(x: r.minX + r.width * 0.18, y: r.maxY - r.height * 0.28))
            p.addLine(to: CGPoint(x: r.minX + r.width * 0.28, y: r.minY + r.height * 0.34))
            p.addLine(to: CGPoint(x: r.midX, y: r.minY + r.height * 0.48))
            p.addLine(to: CGPoint(x: r.maxX - r.width * 0.28, y: r.minY + r.height * 0.34))
            p.addLine(to: CGPoint(x: r.maxX - r.width * 0.18, y: r.maxY - r.height * 0.28))
            p.closeSubpath()
        default:
            p.addEllipse(in: r.insetBy(dx: r.width * 0.18, dy: r.height * 0.18))
        }
        return p
    }

    private static func phonePath(in r: CGRect, down: Bool) -> Path {
        var p = Path()
        p.addRoundedRect(in: r.insetBy(dx: r.width * 0.28, dy: r.height * 0.12), cornerSize: .init(width: r.width * 0.1, height: r.height * 0.1))
        if down {
            p.move(to: CGPoint(x: r.minX + r.width * 0.34, y: r.minY + r.height * 0.34))
            p.addQuadCurve(to: CGPoint(x: r.maxX - r.width * 0.34, y: r.minY + r.height * 0.34), control: CGPoint(x: r.midX, y: r.maxY - r.height * 0.08))
        }
        return p
    }

    private static func iphonePath(in r: CGRect, slash: Bool) -> Path {
        var p = devicePhonePath(in: r)
        if slash {
            p.move(to: CGPoint(x: r.minX + r.width * 0.22, y: r.maxY - r.height * 0.18))
            p.addLine(to: CGPoint(x: r.maxX - r.width * 0.22, y: r.minY + r.height * 0.18))
        }
        return p
    }

    private static func ellipsisCirclePath(in r: CGRect) -> Path {
        var p = Path()
        p.addEllipse(in: r.insetBy(dx: r.width * 0.12, dy: r.height * 0.12))
        for i in 0..<3 {
            p.addEllipse(in: CGRect(x: r.minX + r.width * (0.3 + CGFloat(i) * 0.16), y: r.midY - r.height * 0.04, width: r.width * 0.08, height: r.height * 0.08))
        }
        return p
    }

    private static func deleteLeftPath(in r: CGRect) -> Path {
        var p = Path()
        p.addRoundedRect(in: r.insetBy(dx: r.width * 0.14, dy: r.height * 0.28), cornerSize: .init(width: 4, height: 4))
        p.move(to: CGPoint(x: r.maxX - r.width * 0.22, y: r.minY + r.height * 0.28))
        p.addLine(to: CGPoint(x: r.maxX - r.width * 0.36, y: r.midY))
        p.addLine(to: CGPoint(x: r.maxX - r.width * 0.22, y: r.maxY - r.height * 0.28))
        return p
    }

    private static func biometricsPath(in r: CGRect) -> Path {
        var p = Path()
        p.addRoundedRect(in: r.insetBy(dx: r.width * 0.18, dy: r.height * 0.14), cornerSize: .init(width: r.width * 0.2, height: r.height * 0.2))
        for i in 0..<3 {
            p.addRoundedRect(in: CGRect(x: r.minX + r.width * (0.24 + CGFloat(i) * 0.12), y: r.minY + r.height * 0.34, width: r.width * 0.08, height: r.height * 0.24), cornerSize: .init(width: 2, height: 2))
        }
        return p
    }

    private static func utilityPath(in r: CGRect, kind: String) -> Path {
        switch kind {
        case "key":
            var p = Path()
            p.addEllipse(in: CGRect(x: r.minX + r.width * 0.14, y: r.minY + r.height * 0.22, width: r.width * 0.22, height: r.height * 0.22))
            p.move(to: CGPoint(x: r.minX + r.width * 0.34, y: r.midY))
            p.addLine(to: CGPoint(x: r.maxX - r.width * 0.14, y: r.midY))
            p.move(to: CGPoint(x: r.maxX - r.width * 0.24, y: r.midY))
            p.addLine(to: CGPoint(x: r.maxX - r.width * 0.24, y: r.midY + r.height * 0.1))
            return p
        case "speaker.wave.2":
            var p = Path()
            p.move(to: CGPoint(x: r.minX + r.width * 0.22, y: r.minY + r.height * 0.34))
            p.addLine(to: CGPoint(x: r.minX + r.width * 0.34, y: r.minY + r.height * 0.34))
            p.addLine(to: CGPoint(x: r.minX + r.width * 0.46, y: r.minY + r.height * 0.24))
            p.addLine(to: CGPoint(x: r.minX + r.width * 0.46, y: r.maxY - r.height * 0.24))
            p.addLine(to: CGPoint(x: r.minX + r.width * 0.34, y: r.maxY - r.height * 0.34))
            p.addLine(to: CGPoint(x: r.minX + r.width * 0.22, y: r.maxY - r.height * 0.34))
            p.closeSubpath()
            p.move(to: CGPoint(x: r.minX + r.width * 0.56, y: r.midY - r.height * 0.08))
            p.addQuadCurve(to: CGPoint(x: r.minX + r.width * 0.56, y: r.midY + r.height * 0.08), control: CGPoint(x: r.maxX - r.width * 0.12, y: r.midY))
            return p
        default:
            return gearPath(in: r)
        }
    }

    private static func documentPath(in r: CGRect, kind: String) -> Path {
        var p = Path()
        p.addRoundedRect(in: r.insetBy(dx: r.width * 0.18, dy: r.height * 0.14), cornerSize: .init(width: 4, height: 4))
        p.move(to: CGPoint(x: r.minX + r.width * 0.28, y: r.minY + r.height * 0.34))
        p.addLine(to: CGPoint(x: r.maxX - r.width * 0.28, y: r.minY + r.height * 0.34))
        p.move(to: CGPoint(x: r.minX + r.width * 0.28, y: r.midY))
        p.addLine(to: CGPoint(x: r.maxX - r.width * 0.34, y: r.midY))
        return p
    }

    private static func teamWavePath(in r: CGRect) -> Path {
        var p = profileUserPath(in: r)
        p.move(to: CGPoint(x: r.minX + r.width * 0.08, y: r.midY))
        p.addQuadCurve(to: CGPoint(x: r.minX + r.width * 0.02, y: r.midY - r.height * 0.1), control: CGPoint(x: r.minX + r.width * 0.02, y: r.midY))
        p.move(to: CGPoint(x: r.maxX - r.width * 0.08, y: r.midY))
        p.addQuadCurve(to: CGPoint(x: r.maxX - r.width * 0.02, y: r.midY - r.height * 0.1), control: CGPoint(x: r.maxX - r.width * 0.02, y: r.midY))
        return p
    }

    private static func pairPeoplePath(in r: CGRect) -> Path {
        var p = Path()
        p.addEllipse(in: CGRect(x: r.minX + r.width * 0.16, y: r.minY + r.height * 0.18, width: r.width * 0.22, height: r.height * 0.2))
        p.addEllipse(in: CGRect(x: r.minX + r.width * 0.56, y: r.minY + r.height * 0.18, width: r.width * 0.22, height: r.height * 0.2))
        p.addRoundedRect(in: CGRect(x: r.minX + r.width * 0.14, y: r.minY + r.height * 0.44, width: r.width * 0.3, height: r.height * 0.3), cornerSize: .init(width: 6, height: 6))
        p.addRoundedRect(in: CGRect(x: r.minX + r.width * 0.54, y: r.minY + r.height * 0.44, width: r.width * 0.3, height: r.height * 0.3), cornerSize: .init(width: 6, height: 6))
        return p
    }

    private static func teamSequencePath(in r: CGRect) -> Path {
        var p = teamPath(in: r, filled: true)
        p.move(to: CGPoint(x: r.minX + r.width * 0.12, y: r.midY))
        p.addLine(to: CGPoint(x: r.minX + r.width * 0.04, y: r.midY))
        p.move(to: CGPoint(x: r.maxX - r.width * 0.12, y: r.midY))
        p.addLine(to: CGPoint(x: r.maxX - r.width * 0.04, y: r.midY))
        return p
    }

    private static func sparklesPath(in r: CGRect) -> Path {
        var p = Path()
        p.move(to: CGPoint(x: r.midX, y: r.minY + r.height * 0.14))
        p.addLine(to: CGPoint(x: r.midX, y: r.maxY - r.height * 0.14))
        p.move(to: CGPoint(x: r.minX + r.width * 0.18, y: r.midY))
        p.addLine(to: CGPoint(x: r.maxX - r.width * 0.18, y: r.midY))
        p.move(to: CGPoint(x: r.minX + r.width * 0.24, y: r.minY + r.height * 0.24))
        p.addLine(to: CGPoint(x: r.maxX - r.width * 0.24, y: r.maxY - r.height * 0.24))
        return p
    }

    private static func paperplanePath(in r: CGRect) -> Path {
        var p = Path()
        p.move(to: CGPoint(x: r.minX + r.width * 0.16, y: r.maxY - r.height * 0.28))
        p.addLine(to: CGPoint(x: r.maxX - r.width * 0.16, y: r.minY + r.height * 0.3))
        p.addLine(to: CGPoint(x: r.minX + r.width * 0.28, y: r.minY + r.height * 0.42))
        p.closeSubpath()
        return p
    }

    private static func thumbsPath(in r: CGRect) -> Path {
        var p = Path()
        p.addRoundedRect(in: CGRect(x: r.minX + r.width * 0.34, y: r.minY + r.height * 0.24, width: r.width * 0.2, height: r.height * 0.42), cornerSize: .init(width: 6, height: 6))
        p.addRoundedRect(in: CGRect(x: r.minX + r.width * 0.22, y: r.minY + r.height * 0.48, width: r.width * 0.44, height: r.height * 0.18), cornerSize: .init(width: 6, height: 6))
        return p
    }

    private static func slidersPath(in r: CGRect) -> Path {
        var p = Path()
        let ys: [CGFloat] = [0.28, 0.5, 0.72]
        let xs: [CGFloat] = [0.58, 0.36, 0.62]
        for (i, y) in ys.enumerated() {
            p.move(to: CGPoint(x: r.minX + r.width * 0.16, y: r.minY + r.height * y))
            p.addLine(to: CGPoint(x: r.maxX - r.width * 0.16, y: r.minY + r.height * y))
            p.addEllipse(in: CGRect(x: r.minX + r.width * xs[i] - r.width * 0.07, y: r.minY + r.height * y - r.height * 0.07, width: r.width * 0.14, height: r.height * 0.14))
        }
        return p
    }

    private static func warningPath(in r: CGRect) -> Path {
        var p = Path()
        p.move(to: CGPoint(x: r.midX, y: r.minY + r.height * 0.14))
        p.addLine(to: CGPoint(x: r.maxX - r.width * 0.16, y: r.maxY - r.height * 0.2))
        p.addLine(to: CGPoint(x: r.minX + r.width * 0.16, y: r.maxY - r.height * 0.2))
        p.closeSubpath()
        p.move(to: CGPoint(x: r.midX, y: r.minY + r.height * 0.42))
        p.addLine(to: CGPoint(x: r.midX, y: r.minY + r.height * 0.62))
        p.addEllipse(in: CGRect(x: r.midX - r.width * 0.04, y: r.maxY - r.height * 0.3, width: r.width * 0.08, height: r.height * 0.08))
        return p
    }

    private static func verifiedPath(in r: CGRect) -> Path {
        var p = shieldPath(in: r, checkered: false)
        p.move(to: CGPoint(x: r.minX + r.width * 0.34, y: r.midY))
        p.addLine(to: CGPoint(x: r.midX - r.width * 0.02, y: r.midY + r.height * 0.1))
        p.addLine(to: CGPoint(x: r.maxX - r.width * 0.28, y: r.minY + r.height * 0.36))
        return p
    }

    private static func profileBadgePath(in r: CGRect, clock: Bool) -> Path {
        var p = profileUserPath(in: r)
        p.addEllipse(in: CGRect(x: r.maxX - r.width * 0.24, y: r.maxY - r.height * 0.24, width: r.width * 0.14, height: r.height * 0.14))
        if clock {
            p.move(to: CGPoint(x: r.maxX - r.width * 0.17, y: r.maxY - r.height * 0.17))
            p.addLine(to: CGPoint(x: r.maxX - r.width * 0.12, y: r.maxY - r.height * 0.12))
        }
        return p
    }

    private static func infoGlyphPath(in r: CGRect, kind: String) -> Path {
        if kind == "questionmark.circle" { return helpBubblePath(in: r) }
        return aboutInfoPath(in: r)
    }

    private static func businessPath(in r: CGRect, kind: String) -> Path {
        if kind == "briefcase" {
            var p = Path()
            p.addRoundedRect(in: CGRect(x: r.minX + r.width * 0.16, y: r.minY + r.height * 0.34, width: r.width * 0.68, height: r.height * 0.42), cornerSize: .init(width: 4, height: 4))
            p.move(to: CGPoint(x: r.minX + r.width * 0.34, y: r.minY + r.height * 0.34))
            p.addLine(to: CGPoint(x: r.minX + r.width * 0.34, y: r.minY + r.height * 0.24))
            p.addLine(to: CGPoint(x: r.maxX - r.width * 0.34, y: r.minY + r.height * 0.24))
            p.addLine(to: CGPoint(x: r.maxX - r.width * 0.34, y: r.minY + r.height * 0.34))
            return p
        }
        var p = Path()
        p.addEllipse(in: r.insetBy(dx: r.width * 0.14, dy: r.height * 0.14))
        p.move(to: CGPoint(x: r.midX, y: r.minY + r.height * 0.34))
        p.addLine(to: CGPoint(x: r.midX, y: r.midY))
        return p
    }

    private static func micPath(in r: CGRect) -> Path {
        var p = Path()
        let capsule = CGRect(x: r.midX - r.width * 0.12, y: r.minY + r.height * 0.14, width: r.width * 0.24, height: r.height * 0.42)
        p.addRoundedRect(in: capsule, cornerSize: .init(width: r.width * 0.12, height: r.height * 0.12))
        p.move(to: CGPoint(x: r.midX, y: capsule.maxY))
        p.addLine(to: CGPoint(x: r.midX, y: r.maxY - r.height * 0.28))
        p.move(to: CGPoint(x: r.midX - r.width * 0.18, y: r.maxY - r.height * 0.24))
        p.addQuadCurve(
            to: CGPoint(x: r.midX + r.width * 0.18, y: r.maxY - r.height * 0.24),
            control: CGPoint(x: r.midX, y: r.maxY - r.height * 0.1)
        )
        p.move(to: CGPoint(x: r.midX - r.width * 0.08, y: r.minY + r.height * 0.24))
        p.addLine(to: CGPoint(x: r.midX + r.width * 0.08, y: r.minY + r.height * 0.24))
        return p
    }

    private static func playPath(in r: CGRect) -> Path {
        var p = Path()
        p.move(to: CGPoint(x: r.minX + r.width * 0.34, y: r.minY + r.height * 0.24))
        p.addLine(to: CGPoint(x: r.maxX - r.width * 0.28, y: r.midY))
        p.addLine(to: CGPoint(x: r.minX + r.width * 0.34, y: r.maxY - r.height * 0.24))
        p.closeSubpath()
        return p
    }

    private static func pausePath(in r: CGRect) -> Path {
        var p = Path()
        p.addRoundedRect(in: CGRect(x: r.minX + r.width * 0.28, y: r.minY + r.height * 0.24, width: r.width * 0.12, height: r.height * 0.52), cornerSize: .init(width: 2, height: 2))
        p.addRoundedRect(in: CGRect(x: r.maxX - r.width * 0.4, y: r.minY + r.height * 0.24, width: r.width * 0.12, height: r.height * 0.52), cornerSize: .init(width: 2, height: 2))
        return p
    }

    private static func waveformPath(in r: CGRect) -> Path {
        var p = Path()
        let bars: [CGFloat] = [0.34, 0.56, 0.28, 0.62, 0.4, 0.52, 0.3]
        for (index, height) in bars.enumerated() {
            let x = r.minX + r.width * (0.16 + CGFloat(index) * 0.12)
            let bar = CGRect(x: x, y: r.midY - r.height * height * 0.24, width: r.width * 0.06, height: r.height * height * 0.48)
            p.addRoundedRect(in: bar, cornerSize: .init(width: 1.5, height: 1.5))
        }
        return p
    }

    private static func locationPath(in r: CGRect) -> Path {
        var p = Path()
        p.move(to: CGPoint(x: r.midX, y: r.minY + r.height * 0.12))
        p.addCurve(
            to: CGPoint(x: r.midX, y: r.maxY - r.height * 0.22),
            control1: CGPoint(x: r.maxX - r.width * 0.12, y: r.minY + r.height * 0.22),
            control2: CGPoint(x: r.maxX - r.width * 0.12, y: r.midY + r.height * 0.08)
        )
        p.addCurve(
            to: CGPoint(x: r.midX, y: r.minY + r.height * 0.12),
            control1: CGPoint(x: r.minX + r.width * 0.12, y: r.midY + r.height * 0.08),
            control2: CGPoint(x: r.minX + r.width * 0.12, y: r.minY + r.height * 0.22)
        )
        p.closeSubpath()
        p.addEllipse(in: CGRect(x: r.midX - r.width * 0.08, y: r.maxY - r.height * 0.28, width: r.width * 0.16, height: r.height * 0.16))
        return p
    }
}
