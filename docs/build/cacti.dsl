<!DOCTYPE style-sheet PUBLIC "-//James Clark//DTD DSSSL Style Sheet//EN" [

<!ENTITY % html "IGNORE">
<!ENTITY % print "IGNORE">

<![ %html; [
<!ENTITY docbook.dsl PUBLIC "-//Norman Walsh//DOCUMENT DocBook HTML Stylesheet//EN" CDATA DSSSL>
]]>
<![ %print; [
<!ENTITY docbook.dsl PUBLIC "-//Norman Walsh//DOCUMENT DocBook Print Stylesheet//EN" CDATA DSSSL>

]]>
]>

<style-sheet>
<style-specification id="print" use="docbook">
<style-specification-body> 

; Page
(define %two-side% #f)
(define %paper-type% "USletter")

(define %page-width%
 (case %paper-type%
    (("A4") 210mm)
    (("USletter") 8.5in)
    (("USlandscape") 11in)))

(define %page-height%
 (case %paper-type%
    (("A4") 297mm)
    (("USletter") 11in)
    (("USlandscape") 8.5in)))

; TOC
(define %section-autolabel% #t)
(define %label-preface-sections% #f)

; Links
(define %show-ulinks% #t)
(define %footnote-ulinks% #t)

; Misc
(define tex-backend #t)

; Formatting
(define %line-spacing-factor% 1.1)
(define %indent-programlisting-lines% #f)
(define %indent-screen-lines% #f)

; Book
(define %generate-book-titlepage% #t)
(define %generate-book-titlepage-on-separate-page% #f)
(define %generate-book-toc% #t)

; Part
(define %generate-part-toc% #f)
(define %generate-part-toc-on-titlepage% #t)
(define %generate-part-titlepage% #f)
(define %generate-partintro-on-titlepage% #t)

; Chapter
(define %chapter-autolabel% #t)
(define %chap-app-running-head-autolabel% #t)

; Article
(define %generate-article-titlepage% #t)
(define %generate-article-toc% #t)
(define %generate-article-titlepage-on-separate-page% #t)
(define %generate-article-toc-on-titlepage% #t)
(define %article-page-number-restart% #f)

; Styles
(element application ($mono-seq$))
(element filename ($mono-seq$))
(element function ($mono-seq$))
(element guibutton ($bold-seq$))
(element guiicon ($bold-seq$))
(element guilabel ($italic-seq$))
(element guimenu ($bold-seq$))
(element guimenuitem ($bold-seq$))
(element hardware ($bold-mono-seq$))
(element keycap ($bold-seq$))
(element literal ($mono-seq$))
(element parameter ($italic-mono-seq$))
(element prompt ($mono-seq$))
(element symbol ($charseq$))
(element emphasis ($italic-seq$))
(element question ($bold-seq$))

(define para-style
  (style
   font-size: %bf-size%
   font-weight: 'medium
   font-posture: 'upright
   font-family-name: %body-font-family%
   line-spacing: (* %bf-size% %line-spacing-factor%)))

(define %bf-size%
 (case %visual-acuity%
    (("tiny") 8pt)
    (("normal") 10pt)
    (("presbyopic") 12pt)
    (("large-type") 24pt)))

(define (toc-depth nd)
  (if (string=? (gi nd) (normalize "book"))
      3
      (if (string=? (gi nd) (normalize "appendix"))
        0
        1)))

(define-unit em %bf-size%)

; Titles
(define ($object-titles-after$)
  (list (normalize "figure")))

; Fonts
(define %visual-acuity% "normal")
(define %title-font-family% "Helvetica")
(define %body-font-family% "Palatino")
(define %mono-font-family% "Courier New")
(define %hsize-bump-factor% 1.1)

; Margins

(define %left-right-margin% 6pi)
(define %header-margin% 1pi)
(define %footer-margin% 4pi)
(define %body-start-indent% 0pi)
(define %left-margin% 4pi)
(define %right-margin% 4pi)

(define %top-margin%
(if (equal? %visual-acuity% "large-type")
      3pi
      3pi))

(define %bottom-margin% 
 (if (equal? %visual-acuity% "large-type")
      2pi 
      2pi))

(define %text-width% (- %page-width% (+ %left-margin% %right-margin%)))
(define %body-width% (- %text-width% %body-start-indent%))
(define %para-sep% (/ %bf-size% 2.0))
(define %block-sep% (* %para-sep% 2.0))
(define %block-start-indent% 0pt)

(define %admon-graphics%
 #f)

;;Where are the admon graphics?
(define %admon-graphics-path%
 "../images/")

; Quadding
(define %default-quadding% 'justify)
(define %component-title-quadding% 'start)
(define %section-title-quadding% 'start)
(define %section-subtitle-quadding% 'start)
(define %article-title-quadding% 'center)
(define %article-subtitle-quadding% 'center)
(define %division-subtitle-quadding% 'start)
(define %component-subtitle-quadding% 'start)

; Functions
(define (OLSTEP)
  (case
   (modulo (length (hierarchical-number-recursive "ORDEREDLIST")) 4)
	((1) 1.2em)
	((2) 1.2em)
	((3) 1.6em)
	((0) 1.4em)))

(define (ILSTEP) 1.0em)

(define (PROCSTEP ilvl)
  (if (> ilvl 1) 1.8em 1.4em))

(define (PROCWID ilvl)
  (if (> ilvl 1) 1.8em 1.4em))

(define ($comptitle$)
  (make paragraph
	font-family-name: %title-font-family%
	font-weight: 'bold
	font-size: (HSIZE 2)
	line-spacing: (* (HSIZE 2) %line-spacing-factor%)
	space-before: (* (HSIZE 2) %head-before-factor%)
	space-after: (* (HSIZE 2) %head-after-factor%)
	start-indent: 0pt
	first-line-start-indent: 0pt
	quadding: 'start
	keep-with-next?: #t
	(process-children-trim)))

; Ignore
(element TITLEABBREV (empty-sosofo))
(element SUBTITLE (empty-sosofo))
(element SETINFO (empty-sosofo))
(element BOOKINFO (empty-sosofo))
(element BIBLIOENTRY (empty-sosofo))
(element BIBLIOMISC (empty-sosofo))
(element BOOKBIBLIO (empty-sosofo))
(element SERIESINFO (empty-sosofo))
(element DOCINFO (empty-sosofo))
(element ARTHEADER (empty-sosofo))

(define ($peril$)
  (let* ((title     (select-elements 
		     (children (current-node)) (normalize "title")))
	 (has-title (not (node-list-empty? title)))
	 (adm-title (if has-title 
			(make sequence
			  (with-mode title-sosofo-mode
			    (process-node-list (node-list-first title))))
			(literal
			 (gentext-element-name 
			  (current-node)))))
	 (hs (HSIZE 2)))
  (if %admon-graphics%
      ($graphical-admonition$)
      (make display-group
	space-before: %block-sep%
	space-after: %block-sep%
	font-family-name: %admon-font-family%
	font-size: (- %bf-size% 1pt)
	font-weight: 'medium
	font-posture: 'upright
	line-spacing: (* (- %bf-size% 1pt) %line-spacing-factor%)
	(make box
	  display?: #t
	  box-type: 'border
	  line-thickness: .5pt
	  start-indent: (+ (inherited-start-indent) (* 2 (ILSTEP)) 2pt)
	  end-indent: (inherited-end-indent)
	  (make paragraph
	    space-before: %para-sep%
	    space-after: %para-sep%
	    start-indent: 1em
	    end-indent: 1em
	    font-family-name: %title-font-family%
	    font-weight: 'bold
	    font-size: hs
	    line-spacing: (* hs %line-spacing-factor%)
	    quadding: 'center
	    keep-with-next?: #t
	    adm-title)
	  (process-children))))))

;; Norm's stylesheets are smart about working out what sort of
;; object to display.  But this bites us.  Since we know that the
;; first item is going to be displayable, always use that.
(define (find-displayable-object objlist notlist extlist)
  (let loop ((nl objlist))
    (if (node-list-empty? nl)
      (empty-node-list)
	(let* ((objdata  (node-list-filter-by-gi
			  (children (node-list-first nl))
			  (list (normalize "videodata")
				(normalize "audiodata")
				(normalize "imagedata"))))
	       (filename (data-filename objdata))
	       (extension (file-extension filename))
	       (notation (attribute-string (normalize "format") objdata)))
	  (node-list-first nl)))))

;; Including bitmaps in the PS and PDF output tends to scale them
;; horribly.  The solution is to scale them down by 50%.
;;
;; You could do this with 'imagedata scale="50"'  in the source,
;; but that will affect all the output formats that we use (because
;; there is only one 'imagedata' per image).
;;
;; Solution is to have the authors include the "FORMAT" attribute,
;; set to PNG or EPS as appropriate, but to omit the extension.
;; If we're using the tex-backend, and the FORMAT is PNG, and the
;; author hasn't already set a scale, then set scale to 0.5.
;; Otherwise, use the supplied scale, or 1, as appropriate.
(define ($graphic$ fileref
		   #!optional (display #f) (format #f)
			      (scale #f)   (align #f))
  (let* ((graphic-format (if format format ""))
	 (graphic-scale  (if scale
			     (/  (string->number scale) 100)
			     (if (and tex-backend
				      (equal? graphic-format "PNG"))
				  0.5 1)))
	 (graphic-align  (cond ((equal? align (normalize "center"))
				'center)
			       ((equal? align (normalize "right"))
				'end)
			       (else
				'start))))
   (make external-graphic
      entity-system-id: (graphic-file fileref)
      notation-system-id: graphic-format
      scale: graphic-scale
      display?: display
      display-alignment: graphic-align)))

</style-specification-body>
</style-specification>

<style-specification id="html" use="docbook">
<style-specification-body> 

; HTML
(define %html-pubid% "-//W3C//DTD HTML 4.01//EN")
(define %html-ext% ".html")
(define %root-filename% "index")
(define %stylesheet% "manual.css")
(define %use-id-as-filename% #t)

; Book
(define %generate-book-toc% #t)
(define %generate-book-titlepage% #t)

; Part
(define %generate-part-toc% #t)
(define %generate-part-toc-on-titlepage% #t)
(define %generate-part-titlepage% #t)
(define %generate-partintro-on-titlepage% #t)

; Navigation
(define %header-navigation% #t)
(define %footer-navigation% #t)
(define %gentext-nav-use-tables% #t)
(define %gentext-nav-tblwidth% "100%")

; Misc
(define %generate-legalnotice-link% #t)
(define %graphic-default-extension% "png")
(define %para-autolabel% #t)

(define (toc-depth nd)
  (if (string=? (gi nd) (normalize "book"))
      3
      (if (string=? (gi nd) (normalize "appendix"))
        0
        1)))

(define %body-attr%
 (list
   (list "BGCOLOR" "#FFFFFF")
   (list "TEXT" "#000000")
   (list "LINK" "#0000FF")
   (list "VLINK" "#840084")
   (list "ALINK" "#0000FF")))

; Styles
(element emphasis ($bold-seq$))

</style-specification-body>
</style-specification>

<external-specification id="docbook" document="docbook.dsl">

</style-sheet>
