/* Emoji set for the Inbox with comprehensive categories, Christmas & Winter, and smart keyword search */
window.NexusEmoji = (() => {
  const split = (s) => (typeof Intl !== 'undefined' && Intl.Segmenter
    ? [...new Intl.Segmenter(undefined, { granularity: 'grapheme' }).segment(s)].map((x) => x.segment).filter((x) => x.trim())
    : Array.from(s).filter((x) => x.trim()));

  const cats = [
    {
      title: 'Smileys & People',
      icon: '😀',
      list: split('😀😃😄😁😆😅🤣😂🙂🙃🫠😉😊😇🥰😍🤩😘😗😚😙😋😛😜🤪😝🤑🤗🫣🤭🫢🫡🤫🤔🤐🤨😐😑😶🫥😏😒🙄😬🤥😌😔😪🤤😴😷🤒🤕🤢🤮🤧🥵🥶🥴😵🤯🤠🥳🥸😎🤓🧐😕🫤😟🙁😮😯😲😳🥺🥹😦😧😨😰😥😢😭😱😖😣😞😓😩😫🥱😤😡😠🤬😈👿💀☠️💩🤡👹👺👻👽👾🤖😺😸😹😻😼😽🙀😿😾🎅🤶🧑‍🎄👼🦸🧝👋🤚🖐️✋🖖👌🤌🤏✌️🤞🫰🤟🤘🤙👈👉👆👇☝️🫵👍👎✊👊🤛🤜👏🙌🫶👐🤲🤝🙏✍️💅🤳💪🧠👀👶🧒👦👧🧑👨👩🧓👴👵')
    },
    {
      title: 'Christmas & Winter',
      icon: '❄️',
      list: split('❄️☃️⛄🌨️🎄🎅🤶🧑‍🎄🎁🔔🛷🦌🕯️⭐🌟✨🍪🥛🧤🧣🧦🎀🎉🎊🧊')
    },
    {
      title: 'Nature & Animals',
      icon: '🐶',
      list: split('❄️🌨️☃️⛄🧊🌱🌿☘️🍀🪴🍂🍁🍄🌾💐🌷🌹🥀🌺🌸🌼🌻🌞🌝🌛🌜🌚🌕🌖🌗🌘🌑🌒🌓🌔🌙🌎🌍🌏💥💫💦💨🐵🐒🦍🐶🐕🐩🐺🦊🦝🐱🐈🦁🐯🐅🐆🐴🐎🦄🦓🦌🐮🐂🐃🐄🐷🐖🐗🐏🐑🐐🐪🐫🦙🦒🐘🦏🐭🐁🐀🐹🐰🐇🦔🦇🐻🐨🐼🦥🦦🦨🐔🐓🐣🐤🐥🐦🐧🦅🦆🦉🐸🐢🐍🐲🐳🐬🐟🐠🦈🐙🦋🐛🐝🐞')
    },
    {
      title: 'Food & Drink',
      icon: '🍔',
      list: split('🍇🍈🍉🍊🍋🍌🍍🥭🍎🍏🍐🍑🍒🍓🫐🥝🍅🥥🥑🍆🥔🥕🌽🌶️🥒🥬🥦🧄🧅🥜🍞🥐🥖🥨🥯🥞🧇🧀🍖🍗🥩🥓🍔🍟🍕🌭🥪🌮🌯🥙🧆🥚🍳🥘🍲🥣🥗🍿🧈🧂🥫🍱🍘🍙🍚🍛🍜🍝🍠🍢🍣🍤🍥🥮🍡🥟🥠🥡🍦🍧🍨🍩🍪🎂🍰🧁🥧🍫🍬🍭🍮🍯🍼🥛☕🍵🍶🍾🍷🍸🍹🍺🍻🥂🥃🥤🧃🧉')
    },
    {
      title: 'Activities',
      icon: '⚽',
      list: split('⚽🏀🏈⚾🥎🎾🏐🏉🥏🎱🏓🏸🏒🏑🥍🏏🥅⛳🏹🎣🥊🥋🎽🛹🛼🛷⛸️🥌🎿⛷️🏂🏋️🤼🤸⛹️🤺🤾🏌️🏇🧘🏄🏊🤽🚣🧗🚴🚵🎪🎭🎨🎬🎤🎧🎼🎹🥁🎷🎺🎸🎻🎲🎯🎳🎮🎰🧩')
    },
    {
      title: 'Travel & Places',
      icon: '🚗',
      list: split('🚗🚕🚙🚌🚎🏎️🚓🚑🚒🚐🛻🚚🚛🚜🏍️🛵🚲🛴🛺🚨🚔🚍🚘🚖🚡🚠🚟🚃🚋🚝🚄🚅🚆🚇🚈🚉✈️🛩️🛫🛬🚀🛸🚁⛵🚤🛥️🚢⚓🚧⛽🚦🚥🗺️🗿🗽🗼🏰🏯🏟️🎡🎢🎠⛲⛱️🏖️🏝️🏜️🌋⛰️🏔️🗻🏕️⛺🏠🏡🏢🏣🏥🏦🏨🏩🏪🏫🏬🏭')
    },
    {
      title: 'Objects',
      icon: '💡',
      list: split('🎄🎁🔔🕯️🧦🎀💡📱📲💻⌨️🖥️🖨️🖱️🕹️🔦🧯💸💵💴💶💷🪙💰💳💎⚖️🧰🔧🔨⚒️🛠️⛏️🔩⚙️🧱🔗⛓️🧲🔫💣🔪🗡️🛡️🚬⚰️🏺🔮📿🧿💈⚗️🔭🔬🕳️🩹🩺💊💉🩸🧬🦠🧫🧪🌡️🧹🧺🧻🚽🚰🚿🛁🔑🗝️🚪🛋️🛏️🧸🖼️🛍️🛒🎈🎏🎊🎉✉️📧📨📩📦📪📫📮📝📁📂📅📆📈📉📊📋📌📍📎🖇️📏📐✂️🗃️🗄️🗑️🔒🔓')
    },
    {
      title: 'Symbols & Flags',
      icon: '❤️',
      list: split('❤️🧡💛💚💙💜🖤🤍🤎💔❣️💕💞💓💗💖💘💝💟☮️✝️☪️🕉️☸️✡️🔯🕎☯️☦️🛐⛎♈♉♊♋♌♍♎♏♐♑♒♓🆔⚛️✅❌❓❗‼️⁉️💯🔥✨⭐🌟💢💬💭🔔🔕🎵🎶➕➖➗✖️♾️💲💱™️©️®️🔴🟠🟡🟢🔵🟣⚫⚪🟤🔺🔻🔶🔷🏁🚩🎌🏴🏳️🇵🇭🇺🇸🇯🇵🇰🇷🇨🇳🇬🇧🇫🇷🇩🇪🇮🇹🇪🇸🇨🇦🇦🇺🇧🇷🇮🇳🇲🇽🇷🇺🇸🇬🇲🇾🇹🇭🇻🇳🇦🇪')
    },
  ];

  const KEYWORDS = {
    '❄️': ['snow', 'snowflake', 'cold', 'winter', 'christmas', 'ice', 'freeze', 'frost', 'flurry'],
    '☃️': ['snowman', 'snow', 'winter', 'christmas', 'frosty', 'cold'],
    '⛄': ['snowman', 'snow', 'winter', 'cold', 'christmas'],
    '🌨️': ['snow', 'cloud', 'blizzard', 'winter', 'weather', 'cold'],
    '🎄': ['christmas', 'tree', 'holiday', 'xmas', 'december', 'festive'],
    '🎅': ['santa', 'claus', 'christmas', 'father christmas', 'xmas', 'saint nick'],
    '🤶': ['mrs claus', 'santa', 'christmas', 'xmas'],
    '🧑‍🎄': ['santa', 'mx claus', 'christmas', 'xmas'],
    '🎁': ['gift', 'present', 'christmas', 'birthday', 'box', 'package'],
    '🔔': ['bell', 'christmas', 'jingle', 'ring', 'alert'],
    '🛷': ['sled', 'sleigh', 'snow', 'winter', 'christmas'],
    '🦌': ['deer', 'reindeer', 'rudolph', 'christmas', 'animal'],
    '🕯️': ['candle', 'christmas', 'light', 'flame'],
    '⭐': ['star', 'christmas', 'night', 'gold'],
    '🌟': ['star', 'sparkle', 'glowing', 'christmas'],
    '✨': ['sparkles', 'magic', 'shine', 'star'],
    '🧊': ['ice', 'cube', 'cold', 'freeze', 'winter'],
    '❤️': ['heart', 'love', 'valentines', 'red'],
    '👻': ['ghost', 'halloween', 'sadako', 'spooky', 'horror'],
    '🎃': ['pumpkin', 'halloween', 'jack o lantern', 'spooky'],
    '🔪': ['knife', 'friday 13th', 'blade', 'slash'],
    '🩸': ['blood', 'drop', 'freddy', 'bleed'],
    '⚡': ['lightning', 'zap', 'cyberpunk', 'electric', 'volt', 'energy'],
    '👍': ['thumbs up', 'like', 'agree', 'ok', 'yes'],
    '😆': ['laugh', 'lol', 'funny', 'haha'],
    '🥺': ['pleading', 'puppy', 'eyes'],
    '🥰': ['love', 'hearts', 'smile', 'happy'],
    '😮': ['wow', 'surprised', 'gasp', 'omg'],
  };

  const all = Array.from(new Set(cats.flatMap((c) => c.list)));

  const search = (query) => {
    if (!query) return all;
    const q = query.trim().toLowerCase();
    const results = [];
    // 1. Direct character / substring match
    all.forEach((em) => {
      if (em.includes(q)) results.push(em);
    });
    // 2. Keyword dictionary match
    Object.entries(KEYWORDS).forEach(([em, kwList]) => {
      if (kwList.some((kw) => kw.includes(q) || q.includes(kw))) {
        if (!results.includes(em)) results.push(em);
      }
    });
    return results.length ? results : all.filter((em) => em.includes(q));
  };

  return { cats, all, search };
})();
