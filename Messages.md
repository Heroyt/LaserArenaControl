# Possible format

- Minimina ID = 59
-

## Start byte

```
01110010
r
```

## Target (1 bytes)

Broadcast

```
11111111
�             
```

Vesta1 (ID: 1)

```
00000001
\SOH
```

MinaD/Brana (ID: 60)

```
00111100
<       
```

MinaH (ID: 59)

```
00111011
;       
```

MiniMina (ID: 58)

```
00111010
:       
```

## 0 (start data)

```
00000000
\0
```

## Data

---

# Start game

```
Read 7 bytes COM10 -> COM4: 
01110010 11111111 00000000 00000001 01001101 01000101 11110110 
r        �        \0       \SOH     M        E        �      
```

# End game

```
Read 7 bytes COM10 -> COM4: 
01110010 11111111 00000000 00000001 01001101 01001111 11111100 
r        �        \0       \SOH     M        O        �      
```

# Standby packs

```
Read 7 bytes COM10 -> COM4: 
01110010 11111111 00000000 00000001 01001101 01010011 11100000 
r        �        \0       \SOH     M        S        �
```

# Run script

## Step 1 - standby

## Step 2 - ???

```
Read 6 bytes COM10 -> COM4: 
01110010 11111111 00000000 00000000 01000110 10111001 
r        �        \0       \0       F        �        
```

## Step 3 - ??? (possibly loading vests - "Poky" jméno na vestě 1)

```
Read 9 bytes COM10 -> COM4:
01110010 11111111 00000000 00001110 01000001 00000001 01010000 01101111 01101011
r        �        \0       \SO      A        \SOH     P        o        k

Read 11 bytes COM10 -> COM4:
01111001 00100000 00100000 00100000 00100000 00100000 00100000 00100000 00100000 00010001 10001101
y        \SP      \SP      \SP      \SP      \SP      \SP      \SP      \SP              �
```

## Step 4 - ??? (possibly miny)

```
Read 19 bytes COM10 -> COM4: 
01110010 11111111 00000000 00001110 01000001 00111010 01101101 01101001 01101110 01101001 01001101 01101001 01101110 01100001 00100000 00100000 00100000 00100000 00101111 
r        �        \0       \SO      A        :        m        i        n        i        M        i        n        a        \SP      \SP      \SP      \SP      /        

Read 43 bytes COM10 -> COM4: 
10001101 00000000 01110010 11111111 00000000 00001110 01000001 00111011 01001101 01101001 01101110 01100001 01001000 00100000 00100000 00100000 00100000 00100000 00100000 00100000 00101111 11100111 00000000 01110010 11111111 00000000 00001110 01000001 00111100 01001101 01101001 01101110 01100001 01000100 00101111 01000010 01010010 01000001 01001110 01000001 00100000 00101111 10011101 
�        \0       r        �        \0       \SO      A        ;        M        i        n        a        H        \SP      \SP      \SP      \SP      \SP      \SP      \SP      /        �        \0       r        �        \0       \SO      A        <        M        i        n        a        D        /        B        R        A        N        A        \SP      /        �
```