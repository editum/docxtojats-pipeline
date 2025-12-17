# Repository

| Interface | Implementation |
|-----------|----------------|
| `App\Service\Automark\Dom\CslRepositoryInterface` | **`App\Service\Automark\Inf\HashCslRepository`** |

A simple repository that indexes references by different fields to allow searching. The success of searches depends on the quality of the CSL reference list.

Searches can be performed on different indexes using logical operators **`$and`** and **`$or`**.

## Indexing / Possible Searches
- **Id**: each style generates its own identifier.
- **First author**: all possible combinations of first name, last name, and abbreviations are indexed to maximize matches.
- **Other authors**: same approach.
- **Titles**
- **Citations**: direct substitutions listed in the **`_citations`** field.

## Vancouver and AMA

| Interface | Implementation |
|-----------|----------------|
| `App\Service\Automark\Dom\AbstractCitationStyle` | **`App\Service\Automark\Inf\CitationStyle\Ama`** |
| `App\Service\Automark\Dom\AbstractCitationStyle` | **`App\Service\Automark\Inf\CitationStyle\Vancouver`** |

- Only direct searches by ID.  
- For these styles, the index is attempted to be extracted from the *mixed citation* header.

## APA

| Interface | Implementation |
|-----------|----------------|
| `App\Service\Automark\Dom\AbstractCitationStyle` | **`App\Service\Automark\Inf\CitationStyle\Apa`** |

- For APA, searches are more complex and use multiple fields with logical operators **`$and`** and **`$or`**.  
- The location of the reference depends heavily on the quality of the detected reference list.  
- Pay special attention to special characters: for example, `et al.` sometimes contains a non-breaking space (**NBSP**).

### Regex

- **Detect a single citation with multiple authors**
    ```php
    const REGEX_SINGLE_CITATION_MULTI_AUTHORS = '/\s*([a-záéíóúñ0-9\s.,&\-]+?(?:et[  ]+al\.)?)\s*,?\s*(\d{4})(?:,\s*p\.?\s*(\d+))?/ui';

    ```

- **Split possible authors**
    ```php
    const REGEX_SPLIT_AUTHORS = '/\s*(?:,|(?:\by\b)|(?:\band\b)|&|(?:\bet\b))\s*/iu';

    ```

### Searches


#### 1. Direct search using `_citations`
If the reference file already contains the `_citations` field, it will be used directly. Example:

```json
[
    {
        "author": [{"family": "Acarturk","given": "Ceren"}],
        "title": "Emdr for syrian refugees ...",
        "type": "article-journal",
        "container-title": "European Journal of Psychotraumatology",
        "issued": "2015",
        "_id": 1,
        "_citations": ["Acarturk et al. 2015","Batman, 2025"]
    },
    {
        "author": [{"family": "Cloitre","given": "Marylène"}],
        "title": "Evidence for proposed ICD-11 PTSD ...",
        "type": "article-journal",
        "container-title": "European Journal of Psychotraumatology",
        "issued": "2013",
        "_id": 2,
        "_citations": ["Cloitre et al. 2013","Cloitre et al. 2013"]
    }
]
```

#### 2. Search by title and authors
If `_citations` is not found, search combines title and authors with logical operators:

```php
CslRepositoryInterface::AND => [
    CslRepositoryInterface::OR => [
        CslRepositoryInterface::SEARCHBY_TITLE => $title,
        CslRepositoryInterface::AND => [
            CslRepositoryInterface::SEARCHBY_FIRST_AUTHOR      => $author[0],
            CslRepositoryInterface::SEARCHBY_MULTIPLE_AUTHORS  => $nauthors > 1,
            [ CslRepositoryInterface::SEARCHBY_OTHER_AUTHOR => $authors[1] ],
            ...
        ],
    ],
    [ CslRepositoryInterface::SEARCHBY_YEAR => $year ]
]
```

#### 3. Pattern-based search

If the previous search yields no results, attempt a match by pattern:
- First on the title.
- If no match, on the authors.
- In case of ambiguity, the first result found is returned.
