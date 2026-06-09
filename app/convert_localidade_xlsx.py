#!/usr/bin/env python3
import json
import re
import sys
import zipfile
import xml.etree.ElementTree as ET

if len(sys.argv) != 2:
    sys.exit(1)

path = sys.argv[1]

try:
    archive = zipfile.ZipFile(path, 'r')
except Exception:
    sys.exit(1)

namespaces = {
    'main': 'http://schemas.openxmlformats.org/spreadsheetml/2006/main',
    'pkg': 'http://schemas.openxmlformats.org/package/2006/relationships',
}

shared = []
if 'xl/sharedStrings.xml' in archive.namelist():
    content = archive.read('xl/sharedStrings.xml')
    xml = ET.fromstring(content)
    for si in xml.findall('.//main:si', namespaces):
        parts = [t.text or '' for t in si.findall('.//main:t', namespaces)]
        shared.append(''.join(parts).strip())

workbook = ET.fromstring(archive.read('xl/workbook.xml'))
sheets = []
for sheet in workbook.findall('.//main:sheet', namespaces):
    rid = sheet.attrib.get('{http://schemas.openxmlformats.org/officeDocument/2006/relationships}id', '')
    name = sheet.attrib.get('name', '').strip()
    sheets.append({'name': name, 'rid': rid})

relations = ET.fromstring(archive.read('xl/_rels/workbook.xml.rels'))
relmap = {}
for rel in relations.findall('.//pkg:Relationship', namespaces):
    rid = rel.attrib.get('Id')
    target = rel.attrib.get('Target')
    if rid and target:
        relmap[rid] = target


def clean(text):
    return re.sub(r'\s+', ' ', text.strip().lstrip("'"))


def is_top_level_unit(text):
    lower = text.lower()
    if re.search(r'\b(sec\. mun\.|secretaria municipal|secretaria|gabinete do prefeito|gabinete|fundo social)(?=[\s\W]|$)', lower) and not re.search(r'\b(secretário|presidente|diretor|chefe|coordenador|supervisor|gerente)\b', lower):
        return True
    return False


def is_header_row(text):
    lower = text.lower()
    return bool(re.match(r'^(função|nome|portaria|e-mail|email|organograma das secretarias|table\s+\d+)$', lower))


def is_division(text):
    return bool(re.search(r'diretor.*divis', text, re.I))


def is_sector(text):
    if is_division(text):
        return False
    return bool(re.search(r'chefe.*setor|coordenador|supervisor|gerente|diretor.*setor|setor', text, re.I))


def load_rows(target):
    content = archive.read('xl/' + target)
    xml = ET.fromstring(content)
    rows = []
    for row in xml.findall('.//main:row', namespaces):
        row_values = []
        for cell in row.findall('main:c', namespaces):
            value = ''
            cell_type = cell.attrib.get('t', '')
            v = cell.find('main:v', namespaces)
            if v is not None and v.text is not None:
                raw = v.text
                if cell_type == 's' and raw.isdigit() and int(raw) < len(shared):
                    value = shared[int(raw)]
                else:
                    value = raw
            row_values.append(clean(value))
        rows.append(row_values)
    return rows

def build_sections(sheet_name, rows):
    sections = []
    current_secretaria = None
    current_division = None

    for row in rows:
        if not row:
            continue

        text = clean(row[0])
        if not text or is_header_row(text):
            continue

        if is_top_level_unit(text):
            if current_secretaria is not None:
                sections.append(current_secretaria)
            current_secretaria = {'secretaria': text, 'divisoes': []}
            current_division = None
            continue
        
        if current_secretaria is None:
            continue

        if is_division(text):
            current_division = {'name': text, 'setores': []}
            current_secretaria['divisoes'].append(current_division)
            continue

        if is_sector(text):
            if current_division is None:
                current_division = {'name': 'Geral', 'setores': []}
                current_secretaria['divisoes'].append(current_division)
            current_division['setores'].append(text)

    if current_secretaria is not None:
        sections.append(current_secretaria)

    if not sections and sheet_name and not re.match(r'^Table\s+\d+$', sheet_name, re.I):
        current_secretaria = {'secretaria': clean(sheet_name), 'divisoes': []}
        current_division = None

        for row in rows:
            if not row:
                continue

            text = clean(row[0])
            if not text or is_header_row(text) or is_top_level_unit(text):
                continue

            if is_division(text):
                current_division = {'name': text, 'setores': []}
                current_secretaria['divisoes'].append(current_division)
                continue

            if is_sector(text):
                if current_division is None:
                    current_division = {'name': 'Geral', 'setores': []}
                    current_secretaria['divisoes'].append(current_division)
                current_division['setores'].append(text)

        sections.append(current_secretaria)

    return sections


hierarchy = []
for sheet in sheets:
    target = relmap.get(sheet['rid'])
    if not target:
        continue
    rows = load_rows(target)
    if not rows:
        continue
    sheet_name = sheet['name']
    if re.match(r'^Table\s+\d+$', sheet_name, re.I) and rows and rows[0]:
        sheet_name = rows[0][0]

    sections = build_sections(sheet_name, rows)
    hierarchy.extend(sections)

print(json.dumps(hierarchy, ensure_ascii=False))
