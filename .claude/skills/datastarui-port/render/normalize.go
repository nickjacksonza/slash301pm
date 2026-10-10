package main

import (
	"sort"
	"strings"

	xhtml "golang.org/x/net/html"
)

// Canonical form (the PHP twin is ../normalize.php; keep the two in sync):
//   - one node per line, indented 2 spaces per depth, no comments
//   - attributes sorted by name, first duplicate wins, empty value prints as bare name
//   - class tokens sorted, de-duplicated, joined by one space
//   - data-class "{'a': x, 'b': y}" top-level entries sorted (Go builds them from a map, so order is random)
//   - attribute values and text decoded, then re-escaped with & < > (and " in attributes)
//   - text whitespace collapsed to single spaces and trimmed, empty text dropped
//   - self-closing non-void tags become open + close tags; void tags print as <tag ... />
//   - script and style content is kept as one trimmed text node
var voidTags = map[string]bool{"area": true, "base": true, "br": true, "col": true, "embed": true, "hr": true,
	"img": true, "input": true, "link": true, "meta": true, "source": true, "track": true, "wbr": true}

func escText(s string) string {
	s = strings.ReplaceAll(s, "&", "&amp;")
	s = strings.ReplaceAll(s, "<", "&lt;")
	return strings.ReplaceAll(s, ">", "&gt;")
}

func escAttr(s string) string {
	return strings.ReplaceAll(escText(s), `"`, "&quot;")
}

func normalize(src string) string {
	z := xhtml.NewTokenizer(strings.NewReader(src))
	var out []string
	depth := 0
	pad := func() string { return strings.Repeat("  ", depth) }
	for {
		tt := z.Next()
		if tt == xhtml.ErrorToken {
			break
		}
		switch tt {
		case xhtml.TextToken:
			t := strings.Join(strings.Fields(string(z.Text())), " ")
			if t != "" {
				out = append(out, pad()+escText(t))
			}
		case xhtml.StartTagToken, xhtml.SelfClosingTagToken:
			tok := z.Token()
			tag := tok.Data
			seen := map[string]bool{}
			type kv struct{ k, v string }
			var attrs []kv
			for _, a := range tok.Attr {
				if seen[a.Key] {
					continue
				}
				seen[a.Key] = true
				v := a.Val
				if a.Key == "class" {
					v = sortClasses(v)
				}
				if a.Key == "data-class" {
					v = sortObjectEntries(v)
				}
				attrs = append(attrs, kv{a.Key, v})
			}
			sort.Slice(attrs, func(i, j int) bool { return attrs[i].k < attrs[j].k })
			var sb strings.Builder
			sb.WriteString("<" + tag)
			for _, a := range attrs {
				if a.v == "" {
					sb.WriteString(" " + a.k)
				} else {
					sb.WriteString(" " + a.k + `="` + escAttr(a.v) + `"`)
				}
			}
			if voidTags[tag] {
				sb.WriteString(" />")
				out = append(out, pad()+sb.String())
				continue
			}
			sb.WriteString(">")
			out = append(out, pad()+sb.String())
			if tt == xhtml.SelfClosingTagToken {
				out = append(out, pad()+"</"+tag+">")
			} else {
				depth++
			}
		case xhtml.EndTagToken:
			tag, _ := z.TagName()
			if voidTags[string(tag)] {
				continue
			}
			if depth > 0 {
				depth--
			}
			out = append(out, pad()+"</"+string(tag)+">")
		}
	}
	return strings.Join(out, "\n")
}

// classList returns "<tag> <decoded sorted class string>" for every element that has a class attribute.
func classList(src string) string {
	z := xhtml.NewTokenizer(strings.NewReader(src))
	var out []string
	for {
		tt := z.Next()
		if tt == xhtml.ErrorToken {
			break
		}
		if tt != xhtml.StartTagToken && tt != xhtml.SelfClosingTagToken {
			continue
		}
		tok := z.Token()
		for _, a := range tok.Attr {
			if a.Key == "class" {
				out = append(out, tok.Data+"\t"+sortClasses(a.Val))
				break
			}
		}
	}
	return strings.Join(out, "\n")
}

// sortObjectEntries sorts the top-level "key: expr" entries of a JS object literal such as
// "{'a': $x, 'b': $y === 'z'}". Commas inside quotes or brackets do not split. Other values pass through.
func sortObjectEntries(v string) string {
	t := strings.TrimSpace(v)
	if len(t) < 2 || t[0] != '{' || t[len(t)-1] != '}' {
		return v
	}
	inner := t[1 : len(t)-1]
	var parts []string
	depth, start := 0, 0
	var quote byte
	for i := 0; i < len(inner); i++ {
		c := inner[i]
		switch {
		case quote != 0:
			if c == '\\' {
				i++
			} else if c == quote {
				quote = 0
			}
		case c == '\'' || c == '"' || c == '`':
			quote = c
		case c == '(' || c == '[' || c == '{':
			depth++
		case c == ')' || c == ']' || c == '}':
			depth--
		case c == ',' && depth == 0:
			parts = append(parts, strings.TrimSpace(inner[start:i]))
			start = i + 1
		}
	}
	parts = append(parts, strings.TrimSpace(inner[start:]))
	sort.Strings(parts)
	return "{" + strings.Join(parts, ", ") + "}"
}

// sortClasses sorts and de-duplicates class tokens.
func sortClasses(v string) string {
	seen := map[string]bool{}
	var f []string
	for _, t := range strings.Fields(v) {
		if !seen[t] {
			seen[t] = true
			f = append(f, t)
		}
	}
	sort.Strings(f)
	return strings.Join(f, " ")
}
