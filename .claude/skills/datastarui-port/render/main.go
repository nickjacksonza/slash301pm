// render: print the real DatastarUI (Go + templ) HTML for a component, so a PHP port can copy it.
//
// USAGE (run from the repo root; needs Go 1.24+ and network for the first build only)
//
//	go run -C .claude/skills/datastarui-port/render . button --variant=destructive --size=lg --text=Delete
//	go run -C .claude/skills/datastarui-port/render . input --type=email --name=email --id=email --placeholder=you@x.com
//	go run -C .claude/skills/datastarui-port/render . dialog.trigger --dialogID=confirm_delete --text=Open
//	go run -C .claude/skills/datastarui-port/render . toast.item --id=saved --title=Saved --variant=success
//	go run -C .claude/skills/datastarui-port/render . --tree=cases/dialog.json     (one tree, see below)
//	go run -C .claude/skills/datastarui-port/render . --cases=cases/button.json --out=../../../../tests/fixtures/ui
//	go run -C .claude/skills/datastarui-port/render . --list                       (all component names)
//
// FLAGS
//
//	--<Field>=<value>   any args-struct field, case-insensitive, hyphens and underscores ignored
//	                    (--default-open=true, --dialogID=x). Values are coerced to the field type.
//	--attr.<name>=<v>   adds to the Attributes map, e.g. --attr.data-testid=save
//	--json=<json|@file> whole args object as JSON (merged under the flags)
//	--text=<string>     child text (escaped). Use --tree or --cases for nested children.
//	--tree=<file>       render one node tree, see "TREE FORMAT"
//	--cases=<file>      render many named nodes; with --out=<dir> writes <dir>/<name>.html, else prints all
//	--classes           print each element's decoded class string (copy these into PHP as literals)
//	--raw               do not normalise (Go emits class tokens in random order, so raw output is not stable)
//
// OUTPUT: normalised HTML, one node per line, 2 space indent, attributes sorted, class tokens sorted,
// comments dropped, text whitespace collapsed. The PHP side must apply the same normaliser
// (normalize.php next to this folder) before comparing. This removes template whitespace and
// tailwind-merge ordering noise (the Go merge output order changes from run to run).
//
// TREE FORMAT (JSON): {"name":"x","c":"dialog","args":{...},"children":["text",{"html":"<b>raw</b>"},{"c":"dialog.title","children":["Hi"]}]}
// "matrix":{"variant":["default","ghost"],"size":["sm","lg"]} on a node expands to one case per
// combination named <name>.<v1>.<v2> (keys in the order written). Component names come from --list.
//
// PINNED VERSION: github.com/coreycole/datastarui v0.0.0-20260912221123-feb9af0c58ad
// (commit feb9af0c58ade31f8fefc1443b7e7e15ad413242). Bump with:
//
//	cd .claude/skills/datastarui-port/render && go get github.com/coreycole/datastarui@<commit> && go mod tidy
package main

import (
	"bytes"
	"context"
	"encoding/json"
	"fmt"
	"io"
	"os"
	"path/filepath"
	"reflect"
	"sort"
	"strconv"
	"strings"

	"github.com/a-h/templ"
	"github.com/coreycole/datastarui/components/avatar"
	"github.com/coreycole/datastarui/components/breadcrumb"
	"github.com/coreycole/datastarui/components/button"
	"github.com/coreycole/datastarui/components/card"
	"github.com/coreycole/datastarui/components/checkbox"
	"github.com/coreycole/datastarui/components/dialog"
	"github.com/coreycole/datastarui/components/dropdown"
	"github.com/coreycole/datastarui/components/form"
	"github.com/coreycole/datastarui/components/input"
	"github.com/coreycole/datastarui/components/label"
	"github.com/coreycole/datastarui/components/popover"
	selectc "github.com/coreycole/datastarui/components/select"
	"github.com/coreycole/datastarui/components/sheet"
	"github.com/coreycole/datastarui/components/tabs"
	"github.com/coreycole/datastarui/components/textarea"
	"github.com/coreycole/datastarui/components/themetoggle"
	"github.com/coreycole/datastarui/components/toast"
	"github.com/coreycole/datastarui/components/tooltip"
)

type builder func(args map[string]any) (templ.Component, error)

// reg adapts a templ component func(Args) to the JSON/flag driven builder.
func reg[A any](f func(A) templ.Component) builder {
	return func(m map[string]any) (templ.Component, error) {
		var a A
		coerce(reflect.TypeOf(a), m)
		b, err := json.Marshal(m)
		if err != nil {
			return nil, err
		}
		if err := json.Unmarshal(b, &a); err != nil {
			return nil, err
		}
		return f(a), nil
	}
}

// coerce turns flag strings into the field's bool/int type (JSON values pass through unchanged).
func coerce(t reflect.Type, m map[string]any) {
	if t.Kind() != reflect.Struct {
		return
	}
	for k, v := range m {
		s, ok := v.(string)
		if !ok {
			continue
		}
		for i := 0; i < t.NumField(); i++ {
			f := t.Field(i)
			if !strings.EqualFold(f.Name, k) {
				continue
			}
			switch f.Type.Kind() {
			case reflect.Bool:
				if b, err := strconv.ParseBool(s); err == nil {
					m[k] = b
				}
			case reflect.Int, reflect.Int64:
				if n, err := strconv.Atoi(s); err == nil {
					m[k] = n
				}
			}
		}
	}
}

var registry = map[string]builder{
	"avatar":               reg(avatar.Avatar),
	"breadcrumb":           reg(breadcrumb.Breadcrumb),
	"breadcrumb.list":      reg(breadcrumb.BreadcrumbList),
	"breadcrumb.item":      reg(breadcrumb.BreadcrumbItem),
	"breadcrumb.link":      reg(breadcrumb.BreadcrumbLink),
	"breadcrumb.page":      reg(breadcrumb.BreadcrumbPage),
	"breadcrumb.separator": reg(breadcrumb.BreadcrumbSeparator),
	"breadcrumb.ellipsis":  reg(breadcrumb.BreadcrumbEllipsis),
	"button":               reg(button.Button),
	"button.link":          reg(button.LinkButton),
	"card":                 reg(card.Card),
	"card.header":          reg(card.CardHeader),
	"card.title":           reg(card.CardTitle),
	"card.description":     reg(card.CardDescription),
	"card.action":          reg(card.CardAction),
	"card.content":         reg(card.CardContent),
	"card.footer":          reg(card.CardFooter),
	"checkbox":             reg(checkbox.Checkbox),
	"checkbox.indicator":   reg(checkbox.CheckboxIndicator),
	"dialog":               reg(dialog.Dialog),
	"dialog.trigger":       reg(dialog.DialogTrigger),
	"dialog.content":       reg(dialog.DialogContent),
	"dialog.overlay":       reg(dialog.DialogOverlay),
	"dialog.header":        reg(dialog.DialogHeader),
	"dialog.footer":        reg(dialog.DialogFooter),
	"dialog.title":         reg(dialog.DialogTitle),
	"dialog.description":   reg(dialog.DialogDescription),
	"dialog.close":         reg(dialog.DialogClose),
	"dropdown":             reg(dropdown.DropdownMenu),
	"dropdown.trigger":     reg(dropdown.DropdownMenuTrigger),
	"dropdown.content":     reg(dropdown.DropdownMenuContent),
	"dropdown.item":        reg(dropdown.DropdownMenuItem),
	"dropdown.linkitem":    reg(dropdown.DropdownMenuLinkItem),
	"dropdown.label":       reg(dropdown.DropdownMenuLabel),
	"dropdown.separator":   reg(dropdown.DropdownMenuSeparator),
	"dropdown.shortcut":    reg(dropdown.DropdownMenuShortcut),
	"dropdown.group":       reg(dropdown.DropdownMenuGroup),
	"form":                 reg(form.Form),
	"form.item":            reg(form.FormItem),
	"form.label":           reg(form.FormLabel),
	"form.description":     reg(form.FormDescription),
	"form.message":         reg(form.FormMessage),
	"input":                reg(input.Input),
	"label":                reg(label.Label),
	"popover.trigger":      reg(popover.PopoverTrigger),
	"popover.content":      reg(popover.PopoverContent),
	"select":               reg(selectc.Select),
	"select.trigger":       reg(selectc.SelectTrigger),
	"select.value":         reg(selectc.SelectValue),
	"select.content":       reg(selectc.SelectContent),
	"select.item":          reg(selectc.SelectItem),
	"select.label":         reg(selectc.SelectLabel),
	"select.separator":     reg(selectc.SelectSeparator),
	"select.group":         reg(selectc.SelectGroup),
	"sheet":                reg(sheet.Sheet),
	"sheet.trigger":        reg(sheet.SheetTrigger),
	"sheet.content":        reg(sheet.SheetContent),
	"sheet.header":         reg(sheet.SheetHeader),
	"sheet.footer":         reg(sheet.SheetFooter),
	"sheet.title":          reg(sheet.SheetTitle),
	"sheet.description":    reg(sheet.SheetDescription),
	"sheet.close":          reg(sheet.SheetClose),
	"tabs":                 reg(tabs.Tabs),
	"tabs.list":            reg(tabs.TabsList),
	"tabs.trigger":         reg(tabs.TabsTrigger),
	"tabs.content":         reg(tabs.TabsContent),
	"textarea":             reg(textarea.Textarea),
	"themetoggle":          reg(themetoggle.ThemeToggle),
	"toast.container":      reg(toast.ToastContainer),
	"toast.item":           reg(toast.ToastItem),
	"tooltip.trigger":      reg(tooltip.TooltipTrigger),
	"tooltip.content":      reg(tooltip.TooltipContent),
	// toast.trigger takes positional args (toastID string, duration int), so it is hand written.
	"toast.trigger": func(m map[string]any) (templ.Component, error) {
		var id string
		d := 0
		for k, v := range m {
			switch strings.ToLower(k) {
			case "toastid", "id":
				id, _ = v.(string)
			case "duration":
				switch n := v.(type) {
				case string:
					d, _ = strconv.Atoi(n)
				case float64:
					d = int(n)
				}
			}
		}
		return toast.ToastTrigger(id, d), nil
	},
	// Not registered on purpose: calendar, dateinput, datepicker, sidebar, infinitescroll (complex args).
	// To add one: import its package and add `"name": reg(pkg.Templ),` above.
}

// Node is one element of a render tree.
type Node struct {
	Name     string            `json:"name"`
	C        string            `json:"c"`
	Args     map[string]any    `json:"args"`
	Children []json.RawMessage `json:"children"`
	HTML     string            `json:"html"`
	Matrix   orderedMatrix     `json:"matrix"`
	Text     string            `json:"text"`
}

func (n Node) component(ctx context.Context) (templ.Component, error) {
	if n.C == "" {
		if n.HTML != "" {
			return templ.Raw(n.HTML), nil
		}
		return nil, fmt.Errorf("node has neither c nor html")
	}
	bld, ok := registry[strings.ToLower(n.C)]
	if !ok {
		return nil, fmt.Errorf("unknown component %q (try --list)", n.C)
	}
	args := map[string]any{}
	for k, v := range n.Args {
		args[k] = v
	}
	c, err := bld(args)
	if err != nil {
		return nil, fmt.Errorf("%s: %w", n.C, err)
	}
	var kids []templ.Component
	if n.Text != "" {
		kids = append(kids, textNode(n.Text))
	}
	for _, raw := range n.Children {
		var s string
		if json.Unmarshal(raw, &s) == nil {
			kids = append(kids, textNode(s))
			continue
		}
		var child Node
		if err := json.Unmarshal(raw, &child); err != nil {
			return nil, err
		}
		cc, err := child.component(ctx)
		if err != nil {
			return nil, err
		}
		kids = append(kids, cc)
	}
	return templ.ComponentFunc(func(ctx context.Context, w io.Writer) error {
		inner := templ.ComponentFunc(func(ctx context.Context, w io.Writer) error {
			for _, k := range kids {
				if err := k.Render(ctx, w); err != nil {
					return err
				}
			}
			return nil
		})
		return c.Render(templ.WithChildren(ctx, inner), w)
	}), nil
}

var classesMode bool

func renderNode(n Node, raw bool) (string, error) {
	c, err := n.component(context.Background())
	if err != nil {
		return "", err
	}
	var buf bytes.Buffer
	if err := c.Render(context.Background(), &buf); err != nil {
		return "", err
	}
	if raw {
		return buf.String(), nil
	}
	if classesMode {
		return classList(buf.String()), nil
	}
	return normalize(buf.String()), nil
}

// orderedMatrix keeps the key order of the JSON object so case names read <name>.<first>.<second>.
type orderedMatrix []struct {
	Key  string
	Vals []string
}

func (m *orderedMatrix) UnmarshalJSON(b []byte) error {
	dec := json.NewDecoder(bytes.NewReader(b))
	if _, err := dec.Token(); err != nil { // {
		return err
	}
	for dec.More() {
		kt, err := dec.Token()
		if err != nil {
			return err
		}
		var vals []string
		if err := dec.Decode(&vals); err != nil {
			return err
		}
		*m = append(*m, struct {
			Key  string
			Vals []string
		}{kt.(string), vals})
	}
	return nil
}

func expand(n Node) []Node {
	out := []Node{n}
	out[0].Matrix = nil
	for _, axis := range n.Matrix {
		var next []Node
		for _, base := range out {
			for _, v := range axis.Vals {
				c := base
				c.Args = map[string]any{}
				for ak, av := range base.Args {
					c.Args[ak] = av
				}
				c.Args[axis.Key] = v
				c.Name = base.Name + "." + v
				next = append(next, c)
			}
		}
		out = next
	}
	return out
}

func main() {
	flags := map[string]string{}
	attrs := map[string]any{}
	var positional []string
	for _, a := range os.Args[1:] {
		if !strings.HasPrefix(a, "--") {
			positional = append(positional, a)
			continue
		}
		k, v, _ := strings.Cut(strings.TrimPrefix(a, "--"), "=")
		if strings.HasPrefix(k, "attr.") {
			attrs[strings.TrimPrefix(k, "attr.")] = v
			continue
		}
		if v == "" && !strings.Contains(a, "=") {
			v = "true"
		}
		flags[k] = v
	}
	if _, ok := flags["list"]; ok {
		names := make([]string, 0, len(registry))
		for k := range registry {
			names = append(names, k)
		}
		sort.Strings(names)
		fmt.Println(strings.Join(names, "\n"))
		return
	}
	_, raw := flags["raw"]
	_, classesMode = flags["classes"]

	if f := flags["cases"]; f != "" {
		var nodes []Node
		mustJSON(f, &nodes)
		out := flags["out"]
		if out != "" {
			must(os.MkdirAll(out, 0o755))
		}
		for _, n := range nodes {
			for _, e := range expand(n) {
				html, err := renderNode(e, raw)
				must(err)
				if out == "" {
					fmt.Printf("<!-- %s -->\n%s\n", e.Name, html)
					continue
				}
				p := filepath.Join(out, e.Name+".html")
				must(os.WriteFile(p, []byte(html), 0o644))
				fmt.Println("wrote", p)
			}
		}
		return
	}
	if f := flags["tree"]; f != "" {
		var n Node
		mustJSON(f, &n)
		html, err := renderNode(n, raw)
		must(err)
		fmt.Println(html)
		return
	}
	if len(positional) != 1 {
		fmt.Fprintln(os.Stderr, "usage: render <component> [--field=value ...] [--text=T] | --tree=F | --cases=F [--out=D] | --list")
		os.Exit(2)
	}
	args := map[string]any{}
	if j := flags["json"]; j != "" {
		if strings.HasPrefix(j, "@") {
			mustJSON(j[1:], &args)
		} else {
			must(json.Unmarshal([]byte(j), &args))
		}
	}
	for k, v := range flags {
		switch k {
		case "json", "text", "raw", "classes":
			continue
		}
		args[strings.NewReplacer("-", "", "_", "").Replace(k)] = v
	}
	if len(attrs) > 0 {
		args["attributes"] = attrs
	}
	html, err := renderNode(Node{C: positional[0], Args: args, Text: flags["text"]}, raw)
	must(err)
	fmt.Println(html)
}

func mustJSON(path string, v any) {
	var b []byte
	var err error
	if path == "-" {
		b, err = io.ReadAll(os.Stdin)
	} else {
		b, err = os.ReadFile(path)
	}
	must(err)
	must(json.Unmarshal(b, v))
}

func must(err error) {
	if err != nil {
		fmt.Fprintln(os.Stderr, "render:", err)
		os.Exit(1)
	}
}

// textNode renders s as escaped text.
func textNode(s string) templ.Component {
	return templ.ComponentFunc(func(ctx context.Context, w io.Writer) error {
		_, err := io.WriteString(w, templ.EscapeString(s))
		return err
	})
}
