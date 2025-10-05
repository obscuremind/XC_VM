#!/usr/bin/env python
# -*- coding: utf-8 -*-

patterns = [
    ("season", r"(s?([0-9]{1,2}))[ex ]"),
    ("episode", r"([ex]([0-9]{2})(?:[^0-9]|$))"),
    ("year", r"([\[\(]?((?:19[0-9]|20[0-2])[0-9])[\]\)]?)"),
    ("resolution", r"([0-9]{3,4}p)"),
    (
        "quality",
        (
            r"((?:PPV\.)?[HP]DTV|(?:HD)?CAM|B[DR]Rip|TS|(?:PPV "
            r")?WEB-?DL(?: DVDRip)?|HDRip|DVDRip|DVDR"
            r"IP|CamRip|W[EB]BRip|BluRay|DvDScr|hdtv)"
        ),
    ),
    ("codec", r"(xvid|[hx]\.?26[45])"),
    (
        "audio",
        (
            r"(MP3|DD5\.?1|Dual[\- ]Audio|LiNE|H?DTS|AAC(?:\.?2\.0)"
            r"?|AC3(?:\.5\.1)?)"
        ),
    ),
    ("group", r"(- ?([^-]+(?:-={[^-]+-?$)?))$"),
    ("region", r"R[0-9]"),
    ("extended", r"(EXTENDED(:?.CUT)?)"),
    ("hardcoded", r"HC"),
    ("proper", r"PROPER"),
    ("repack", r"REPACK"),
    ("container", r"(MKV|AVI)"),
    ("widescreen", r"WS"),
    ("website", r"^(\[ ?([^\]]+?) ?\])"),
    ("language", r"rus\.eng"),
    ("sbs", r"(?:Half-)?SBS"),
]

types = {
    "season": "integer",
    "episode": "integer",
    "year": "integer",
    "extended": "boolean",
    "hardcoded": "boolean",
    "proper": "boolean",
    "repack": "boolean",
    "widescreen": "boolean",
}
